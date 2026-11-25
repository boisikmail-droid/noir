<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Service\GameHub;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

final class GameSocketServer implements MessageComponentInterface
{
    /** @var array<int, ConnectionInterface> */
    private array $connections = [];

    public function __construct(
        private readonly GameHub $hub,
    ) {
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->connections[$conn->resourceId] = $conn;
        $conn->send($this->encode([
            'type' => 'connected',
            'payload' => ['connectionId' => $conn->resourceId],
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        try {
            $data = json_decode((string) $msg, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->sendError($from, 'Некорректный JSON');

            return;
        }

        $type = $data['type'] ?? '';
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        try {
            match ($type) {
                'hello' => $this->handleHello($from, $payload),
                'artpacks.list' => $this->handleArtPacksList($from),
                'lobby.list' => $this->handleLobbyList($from),
                'lobby.create' => $this->handleLobbyCreate($from, $payload),
                'lobby.join' => $this->handleLobbyJoin($from, $payload),
                'lobby.leave' => $this->handleLobbyLeave($from),
                'lobby.start' => $this->handleLobbyStart($from),
                'game.action' => $this->handleGameAction($from, $payload),
                'game.state' => $this->handleGameState($from),
                'ping' => $from->send($this->encode(['type' => 'pong', 'payload' => []])),
                default => $this->sendError($from, 'Неизвестный тип сообщения: '.$type),
            };
        } catch (\Throwable $e) {
            $this->sendError($from, $e->getMessage());
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        unset($this->connections[$conn->resourceId]);
        $playerId = $this->hub->unregisterConnection($conn->resourceId);
        if ($playerId === null) {
            return;
        }
        $lobbyId = $this->hub->leaveLobby($playerId);
        $this->broadcastLobbies();
        if ($lobbyId !== null) {
            $lobby = $this->hub->getLobby($lobbyId);
            if ($lobby !== null) {
                $this->broadcastToLobby($lobbyId, [
                    'type' => 'lobby.updated',
                    'payload' => ['lobby' => $lobby->toPublicArray()],
                ]);
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        unset($this->connections[$conn->resourceId]);
        $conn->close();
    }

    /** @param array<string, mixed> $payload */
    private function handleHello(ConnectionInterface $from, array $payload): void
    {
        $playerId = (string) ($payload['playerId'] ?? '');
        $playerName = trim((string) ($payload['playerName'] ?? ''));
        if ($playerId === '' || $playerName === '') {
            throw new \InvalidArgumentException('Нужны playerId и playerName');
        }
        $this->hub->registerConnection($from->resourceId, $playerId, $playerName);
        $from->send($this->encode([
            'type' => 'hello.ok',
            'payload' => [
                'playerId' => $playerId,
                'playerName' => $playerName,
            ],
        ]));
        $this->handleLobbyList($from);
    }

    private function handleArtPacksList(ConnectionInterface $from): void
    {
        $from->send($this->encode([
            'type' => 'artpacks.list',
            'payload' => ['packs' => $this->hub->listArtPacks()],
        ]));
    }

    private function handleLobbyList(ConnectionInterface $from): void
    {
        $from->send($this->encode([
            'type' => 'lobby.list',
            'payload' => ['lobbies' => $this->hub->listLobbies()],
        ]));
    }

    /** @param array<string, mixed> $payload */
    private function handleLobbyCreate(ConnectionInterface $from, array $payload): void
    {
        $playerId = $this->requirePlayer($from);
        $name = trim((string) ($payload['name'] ?? 'Лобби'));
        $maxPlayers = (int) ($payload['maxPlayers'] ?? 4);
        $playerName = trim((string) ($payload['playerName'] ?? 'Игрок'));
        $artPack = (string) ($payload['artPack'] ?? 'ink-minimal');

        $result = $this->hub->createLobby(
            $playerId,
            $playerName,
            $name !== '' ? $name : 'Лобби',
            $maxPlayers,
            $artPack
        );
        $from->send($this->encode([
            'type' => 'lobby.joined',
            'payload' => $result,
        ]));
        $this->broadcastLobbies();
    }

    /** @param array<string, mixed> $payload */
    private function handleLobbyJoin(ConnectionInterface $from, array $payload): void
    {
        $playerId = $this->requirePlayer($from);
        $lobbyId = (string) ($payload['lobbyId'] ?? '');
        $playerName = trim((string) ($payload['playerName'] ?? 'Игрок'));
        $result = $this->hub->joinLobby($playerId, $playerName, $lobbyId);
        $from->send($this->encode([
            'type' => 'lobby.joined',
            'payload' => $result,
        ]));
        $this->broadcastLobbies();
        $this->broadcastToLobby($lobbyId, [
            'type' => 'lobby.updated',
            'payload' => ['lobby' => $result['lobby']],
        ]);
    }

    private function handleLobbyLeave(ConnectionInterface $from): void
    {
        $playerId = $this->requirePlayer($from);
        $lobbyId = $this->hub->leaveLobby($playerId);
        $from->send($this->encode([
            'type' => 'lobby.left',
            'payload' => [],
        ]));
        $this->broadcastLobbies();
        if ($lobbyId !== null) {
            $lobby = $this->hub->getLobby($lobbyId);
            if ($lobby !== null) {
                $this->broadcastToLobby($lobbyId, [
                    'type' => 'lobby.updated',
                    'payload' => ['lobby' => $lobby->toPublicArray()],
                ]);
            }
        }
    }

    private function handleLobbyStart(ConnectionInterface $from): void
    {
        $playerId = $this->requirePlayer($from);
        $result = $this->hub->startGame($playerId);
        $lobbyId = $result['lobby']['id'];
        $game = $this->hub->getGame($result['gameId']);
        if ($game === null) {
            throw new \RuntimeException('Не удалось создать игру');
        }

        $this->broadcastLobbies();
        foreach ($this->hub->playersInLobby($lobbyId) as $pid) {
            $this->sendToPlayer($pid, [
                'type' => 'game.started',
                'payload' => [
                    'lobby' => $result['lobby'],
                    'game' => $game->toClientState($pid),
                ],
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function handleGameAction(ConnectionInterface $from, array $payload): void
    {
        $playerId = $this->requirePlayer($from);
        $result = $this->hub->gameAction($playerId, $payload);
        if (!($result['ok'] ?? false)) {
            $this->sendError($from, $result['error'] ?? 'Ошибка хода');

            return;
        }

        $gameId = $result['gameId'] ?? null;
        $game = $gameId ? $this->hub->getGame($gameId) : null;
        if ($game === null) {
            return;
        }

        $lobbyId = $game->getLobbyId();
        foreach ($this->hub->playersInLobby($lobbyId) as $pid) {
            $this->sendToPlayer($pid, [
                'type' => 'game.updated',
                'payload' => [
                    'events' => $result['events'] ?? [],
                    'game' => $game->toClientState($pid),
                ],
            ]);
        }
    }

    private function handleGameState(ConnectionInterface $from): void
    {
        $playerId = $this->requirePlayer($from);
        $lobbyId = $this->hub->getPlayerLobbyId($playerId);
        if ($lobbyId === null) {
            throw new \RuntimeException('Вы не в лобби');
        }
        $lobby = $this->hub->getLobby($lobbyId);
        if ($lobby === null || $lobby->getGameId() === null) {
            throw new \RuntimeException('Игра не начата');
        }
        $game = $this->hub->getGame($lobby->getGameId());
        if ($game === null) {
            throw new \RuntimeException('Игра не найдена');
        }
        $from->send($this->encode([
            'type' => 'game.updated',
            'payload' => [
                'events' => [],
                'game' => $game->toClientState($playerId),
            ],
        ]));
    }

    private function requirePlayer(ConnectionInterface $from): string
    {
        $playerId = $this->hub->getPlayerIdByConnection($from->resourceId);
        if ($playerId === null) {
            throw new \RuntimeException('Сначала отправьте hello');
        }

        return $playerId;
    }

    private function broadcastLobbies(): void
    {
        $message = $this->encode([
            'type' => 'lobby.list',
            'payload' => ['lobbies' => $this->hub->listLobbies()],
        ]);
        foreach ($this->connections as $conn) {
            $conn->send($message);
        }
    }

    /** @param array<string, mixed> $message */
    private function broadcastToLobby(string $lobbyId, array $message): void
    {
        $encoded = $this->encode($message);
        foreach ($this->hub->playersInLobby($lobbyId) as $pid) {
            $this->sendRawToPlayer($pid, $encoded);
        }
    }

    /** @param array<string, mixed> $message */
    private function sendToPlayer(string $playerId, array $message): void
    {
        $this->sendRawToPlayer($playerId, $this->encode($message));
    }

    private function sendRawToPlayer(string $playerId, string $encoded): void
    {
        $resourceId = $this->hub->getConnectionByPlayer($playerId);
        if ($resourceId === null) {
            return;
        }
        $conn = $this->connections[$resourceId] ?? null;
        $conn?->send($encoded);
    }

    private function sendError(ConnectionInterface $conn, string $message): void
    {
        $conn->send($this->encode([
            'type' => 'error',
            'payload' => ['message' => $message],
        ]));
    }

    /** @param array<string, mixed> $data */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
