<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Card\ArtPackCatalog;
use App\Domain\Lobby\Lobby;
use App\Domain\Lobby\LobbyStatus;
use App\Domain\SpyGames\SpyGame;

final class GameHub
{
    /** @var array<string, Lobby> */
    private array $lobbies = [];

    /** @var array<string, SpyGame> */
    private array $games = [];

    /** connection resourceId => playerId */
    /** @var array<int, string> */
    private array $connectionPlayers = [];

    /** playerId => connection resourceId */
    /** @var array<string, int> */
    private array $playerConnections = [];

    /** playerId => lobbyId|null */
    /** @var array<string, string|null> */
    private array $playerLobby = [];

    /** @var array<string, string> playerId => display name */
    private array $playerNames = [];

    public function registerConnection(int $resourceId, string $playerId, string $playerName): void
    {
        // Переподключение: отвязать старый сокет того же игрока
        if (isset($this->playerConnections[$playerId])) {
            $old = $this->playerConnections[$playerId];
            unset($this->connectionPlayers[$old]);
        }
        $this->connectionPlayers[$resourceId] = $playerId;
        $this->playerConnections[$playerId] = $resourceId;
        $this->playerNames[$playerId] = $playerName;
        $this->playerLobby[$playerId] ??= null;
    }

    public function unregisterConnection(int $resourceId): ?string
    {
        $playerId = $this->connectionPlayers[$resourceId] ?? null;
        unset($this->connectionPlayers[$resourceId]);
        if ($playerId !== null) {
            unset($this->playerConnections[$playerId]);
        }

        return $playerId;
    }

    public function getPlayerIdByConnection(int $resourceId): ?string
    {
        return $this->connectionPlayers[$resourceId] ?? null;
    }

    public function getConnectionByPlayer(string $playerId): ?int
    {
        return $this->playerConnections[$playerId] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function listLobbies(): array
    {
        $list = [];
        foreach ($this->lobbies as $lobby) {
            if ($lobby->getStatus() === LobbyStatus::Finished) {
                continue;
            }
            $list[] = $lobby->toPublicArray();
        }
        usort($list, static fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $list;
    }

    /**
     * @return list<array{id: string, label: string, description: string}>
     */
    public function listArtPacks(): array
    {
        return ArtPackCatalog::all();
    }

    /**
     * @return array{lobby: array<string, mixed>}
     */
    public function createLobby(
        string $playerId,
        string $playerName,
        string $name,
        int $maxPlayers,
        string $artPack = ArtPackCatalog::DEFAULT,
    ): array {
        $this->leaveLobby($playerId);

        $id = bin2hex(random_bytes(8));
        $lobby = new Lobby($id, $name, Lobby::MODE_SPY_GAMES, $maxPlayers, $playerId, $artPack);
        $lobby->addPlayer($playerId, $playerName, true);
        $this->lobbies[$id] = $lobby;
        $this->playerLobby[$playerId] = $id;
        $this->playerNames[$playerId] = $playerName;

        return ['lobby' => $lobby->toPublicArray()];
    }

    /**
     * @return array{lobby: array<string, mixed>}
     */
    public function joinLobby(string $playerId, string $playerName, string $lobbyId): array
    {
        $lobby = $this->lobbies[$lobbyId] ?? null;
        if ($lobby === null) {
            throw new \RuntimeException('Лобби не найдено');
        }
        if (!$lobby->canJoin()) {
            throw new \RuntimeException(
                $lobby->getStatus() === LobbyStatus::Gathering
                    ? 'Лобби заполнено'
                    : 'Игра уже начата — подключиться нельзя'
            );
        }

        $this->leaveLobby($playerId);
        $lobby->addPlayer($playerId, $playerName);
        $this->playerLobby[$playerId] = $lobbyId;
        $this->playerNames[$playerId] = $playerName;

        return ['lobby' => $lobby->toPublicArray()];
    }

    public function leaveLobby(string $playerId): ?string
    {
        $lobbyId = $this->playerLobby[$playerId] ?? null;
        if ($lobbyId === null) {
            return null;
        }
        $lobby = $this->lobbies[$lobbyId] ?? null;
        $this->playerLobby[$playerId] = null;
        if ($lobby === null) {
            return $lobbyId;
        }

        if ($lobby->getStatus() === LobbyStatus::Playing) {
            // Во время игры просто отключаем сокет; игрок остаётся в партии
            return $lobbyId;
        }

        $lobby->removePlayer($playerId);
        if ($lobby->playerCount() === 0) {
            unset($this->lobbies[$lobbyId]);
        } elseif ($lobby->getHostPlayerId() === $playerId) {
            // Передаём хоста первому оставшемуся
            $players = $lobby->getPlayers();
            $newHost = array_key_first($players);
            if ($newHost !== null) {
                // Создать новое лобби с новым хостом сложно без мутации — помечаем isHost в toPublic
                // Для простоты: удаляем лобби если хост ушёл на стадии сбора
                unset($this->lobbies[$lobbyId]);
                foreach ($players as $pid => $_) {
                    if ($pid !== $playerId) {
                        $this->playerLobby[$pid] = null;
                    }
                }
            }
        }

        return $lobbyId;
    }

    /**
     * @return array{lobby: array<string, mixed>, gameId: string}
     */
    public function startGame(string $playerId): array
    {
        $lobbyId = $this->playerLobby[$playerId] ?? null;
        if ($lobbyId === null) {
            throw new \RuntimeException('Вы не в лобби');
        }
        $lobby = $this->lobbies[$lobbyId] ?? null;
        if ($lobby === null) {
            throw new \RuntimeException('Лобби не найдено');
        }
        if ($lobby->getHostPlayerId() !== $playerId) {
            throw new \RuntimeException('Только создатель лобби может начать игру');
        }

        $lobby->assignTeams();
        $lobby->markPlaying();

        $gameId = bin2hex(random_bytes(8));
        $players = [];
        foreach ($lobby->getPlayers() as $p) {
            $players[$p['id']] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'team' => $p['team'],
            ];
        }
        $game = new SpyGame($gameId, $lobbyId, $players, $lobby->getArtPack());
        $this->games[$gameId] = $game;
        $lobby->setGameId($gameId);

        return [
            'lobby' => $lobby->toPublicArray(),
            'gameId' => $gameId,
        ];
    }

    public function getLobby(string $lobbyId): ?Lobby
    {
        return $this->lobbies[$lobbyId] ?? null;
    }

    public function getGame(string $gameId): ?SpyGame
    {
        return $this->games[$gameId] ?? null;
    }

    public function getPlayerLobbyId(string $playerId): ?string
    {
        return $this->playerLobby[$playerId] ?? null;
    }

    /**
     * @return array{ok: bool, error?: string, events?: list<array<string, mixed>>, gameId?: string}
     */
    public function gameAction(string $playerId, array $payload): array
    {
        $lobbyId = $this->playerLobby[$playerId] ?? null;
        if ($lobbyId === null) {
            return ['ok' => false, 'error' => 'Вы не в игре'];
        }
        $lobby = $this->lobbies[$lobbyId] ?? null;
        if ($lobby === null || $lobby->getGameId() === null) {
            return ['ok' => false, 'error' => 'Игра не найдена'];
        }
        $game = $this->games[$lobby->getGameId()] ?? null;
        if ($game === null) {
            return ['ok' => false, 'error' => 'Игра не найдена'];
        }

        $result = $game->performAction($playerId, $payload);
        if (($result['ok'] ?? false) && $game->isFinished()) {
            $lobby->markFinished();
        }
        $result['gameId'] = $game->getId();

        return $result;
    }

    /** @return list<string> player ids in lobby */
    public function playersInLobby(string $lobbyId): array
    {
        $lobby = $this->lobbies[$lobbyId] ?? null;
        if ($lobby === null) {
            return [];
        }

        return array_keys($lobby->getPlayers());
    }
}
