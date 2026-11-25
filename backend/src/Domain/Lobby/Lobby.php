<?php

declare(strict_types=1);

namespace App\Domain\Lobby;

use App\Domain\Card\ArtPackCatalog;

final class Lobby
{
    public const MODE_SPY_GAMES = 'spy_games';

    /** Допустимое число игроков по правилам. */
    public const ALLOWED_PLAYER_COUNTS = [3, 4, 5, 6, 8, 9];

    private LobbyStatus $status = LobbyStatus::Gathering;

    /** @var array<string, array{id: string, name: string, isHost: bool, team: int|null}> */
    private array $players = [];

    private ?string $gameId = null;

    private string $artPack;

    public function __construct(
        private readonly string $id,
        private string $name,
        private readonly string $mode,
        private readonly int $maxPlayers,
        private readonly string $hostPlayerId,
        string $artPack = ArtPackCatalog::DEFAULT,
    ) {
        if ($mode !== self::MODE_SPY_GAMES) {
            throw new \InvalidArgumentException('Поддерживается только режим Шпионские игры');
        }
        if (!in_array($maxPlayers, self::ALLOWED_PLAYER_COUNTS, true)) {
            throw new \InvalidArgumentException('Число игроков должно быть 3–6, 8 или 9');
        }
        $this->artPack = ArtPackCatalog::normalize($artPack);
    }

    public function getArtPack(): string
    {
        return $this->artPack;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getMaxPlayers(): int
    {
        return $this->maxPlayers;
    }

    public function getStatus(): LobbyStatus
    {
        return $this->status;
    }

    public function getHostPlayerId(): string
    {
        return $this->hostPlayerId;
    }

    public function getGameId(): ?string
    {
        return $this->gameId;
    }

    public function setGameId(string $gameId): void
    {
        $this->gameId = $gameId;
    }

    /** @return array<string, array{id: string, name: string, isHost: bool, team: int|null}> */
    public function getPlayers(): array
    {
        return $this->players;
    }

    public function playerCount(): int
    {
        return count($this->players);
    }

    public function canJoin(): bool
    {
        return $this->status === LobbyStatus::Gathering
            && $this->playerCount() < $this->maxPlayers;
    }

    public function addPlayer(string $playerId, string $name, bool $isHost = false): void
    {
        if (!$this->canJoin() && !isset($this->players[$playerId])) {
            throw new \RuntimeException('Нельзя войти в это лобби');
        }
        $this->players[$playerId] = [
            'id' => $playerId,
            'name' => $name,
            'isHost' => $isHost || $playerId === $this->hostPlayerId,
            'team' => null,
        ];
    }

    public function removePlayer(string $playerId): void
    {
        unset($this->players[$playerId]);
    }

    public function hasPlayer(string $playerId): bool
    {
        return isset($this->players[$playerId]);
    }

    public function markPlaying(): void
    {
        if ($this->status !== LobbyStatus::Gathering) {
            throw new \RuntimeException('Игра уже начата или завершена');
        }
        if ($this->playerCount() < 3) {
            throw new \RuntimeException('Нужно минимум 3 игрока');
        }
        if (!in_array($this->playerCount(), self::ALLOWED_PLAYER_COUNTS, true)) {
            throw new \RuntimeException('Число игроков должно быть 3–6, 8 или 9');
        }
        $this->status = LobbyStatus::Playing;
    }

    public function markFinished(): void
    {
        $this->status = LobbyStatus::Finished;
    }

    public function assignTeams(): void
    {
        $count = $this->playerCount();
        $ids = array_keys($this->players);

        if ($count === 6 || $count === 8) {
            // Команды по 2; рассадка «напротив» — чередование
            $teamSize = 2;
            $teamCount = (int) ($count / $teamSize);
            foreach ($ids as $i => $id) {
                $this->players[$id]['team'] = $i % $teamCount;
            }
        } elseif ($count === 9) {
            foreach ($ids as $i => $id) {
                $this->players[$id]['team'] = $i % 3;
            }
        } else {
            foreach ($ids as $id) {
                $this->players[$id]['team'] = null;
            }
        }
    }

    /** @return array<string, mixed> */
    public function toPublicArray(): array
    {
        $packMeta = null;
        foreach (ArtPackCatalog::all() as $pack) {
            if ($pack['id'] === $this->artPack) {
                $packMeta = $pack;
                break;
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'mode' => $this->mode,
            'modeLabel' => 'Шпионские игры',
            'status' => $this->status->value,
            'statusLabel' => match ($this->status) {
                LobbyStatus::Gathering => 'Сбор игроков',
                LobbyStatus::Playing => 'Игра идёт',
                LobbyStatus::Finished => 'Завершена',
            },
            'maxPlayers' => $this->maxPlayers,
            'playerCount' => $this->playerCount(),
            'canJoin' => $this->canJoin(),
            'hostPlayerId' => $this->hostPlayerId,
            'players' => array_values($this->players),
            'gameId' => $this->gameId,
            'artPack' => $this->artPack,
            'artPackLabel' => $packMeta['label'] ?? $this->artPack,
        ];
    }
}
