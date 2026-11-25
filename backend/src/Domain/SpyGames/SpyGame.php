<?php

declare(strict_types=1);

namespace App\Domain\SpyGames;

use App\Domain\Card\SuspectCatalog;

/**
 * Режим «Шпионские игры» (Игра 3).
 */
final class SpyGame
{
    private int $size;

    /** @var list<list<BoardCell>> */
    private array $board = [];

    /** @var list<string> id подозреваемых в колоде доказательств */
    private array $evidenceDeck = [];

    /** @var array<string, array{
     *   id: string,
     *   name: string,
     *   team: int|null,
     *   identityId: string|null,
     *   trophies: list<array{suspectId: string, name: string}>
     * }> */
    private array $players = [];

    /** @var list<string> порядок ходов */
    private array $turnOrder = [];

    private int $currentTurnIndex = 0;

    private bool $finished = false;

    /** @var list<string>|null */
    private ?array $winnerIds = null;

    private ?string $winnerLabel = null;

    /** Последний сдвиг: нельзя сразу отменить. */
    private ?array $lastShift = null;

    /** @var list<array{type: string, message: string}> */
    private array $log = [];

    /**
     * @param array<string, array{id: string, name: string, team: int|null}> $players
     */
    public function __construct(
        private readonly string $id,
        private readonly string $lobbyId,
        array $players,
        private readonly string $artPack = 'ink-minimal',
    ) {
        $count = count($players);
        $this->size = self::boardSizeForPlayers($count);
        $this->players = [];
        foreach ($players as $p) {
            $this->players[$p['id']] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'team' => $p['team'],
                'identityId' => null,
                'trophies' => [],
            ];
            $this->turnOrder[] = $p['id'];
        }
        shuffle($this->turnOrder);
        $this->setupBoardAndIdentities();
        $this->log[] = [
            'type' => 'start',
            'message' => sprintf(
                'Игра началась. Поле %dx%d. Первым ходит %s.',
                $this->size,
                $this->size,
                $this->players[$this->turnOrder[0]]['name']
            ),
        ];
    }

    public static function boardSizeForPlayers(int $count): int
    {
        return match (true) {
            $count >= 3 && $count <= 4 => 5,
            $count >= 5 && $count <= 6 => 6,
            $count >= 8 && $count <= 9 => 7,
            default => throw new \InvalidArgumentException('Недопустимое число игроков'),
        };
    }

    public static function trophiesToWin(int $playerCount): int
    {
        return match (true) {
            $playerCount === 3 => 4,
            $playerCount === 4, $playerCount === 5 => 3,
            $playerCount === 6, $playerCount === 8 => 3, // командные
            $playerCount === 9 => 4,
            default => 3,
        };
    }

    public function isTeamMode(): bool
    {
        $count = count($this->players);

        return in_array($count, [6, 8, 9], true);
    }

    private function setupBoardAndIdentities(): void
    {
        $cards = SuspectCatalog::forBoardSize($this->size, $this->artPack);
        shuffle($cards);

        $this->board = [];
        $flat = [];
        $i = 0;
        for ($r = 0; $r < $this->size; ++$r) {
            $row = [];
            for ($c = 0; $c < $this->size; ++$c) {
                $card = $cards[$i++];
                $cell = new BoardCell($card['id'], $card['name'], true);
                $row[] = $cell;
                $flat[] = $card['id'];
            }
            $this->board[] = $row;
        }

        $this->evidenceDeck = $flat;
        shuffle($this->evidenceDeck);

        foreach ($this->turnOrder as $playerId) {
            $identity = array_shift($this->evidenceDeck);
            if ($identity === null) {
                throw new \RuntimeException('Колода доказательств пуста');
            }
            $this->players[$playerId]['identityId'] = $identity;
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLobbyId(): string
    {
        return $this->lobbyId;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function getCurrentPlayerId(): ?string
    {
        if ($this->finished) {
            return null;
        }

        return $this->turnOrder[$this->currentTurnIndex] ?? null;
    }

    public function findPosition(string $suspectId): ?array
    {
        for ($r = 0; $r < $this->size; ++$r) {
            for ($c = 0; $c < $this->size; ++$c) {
                if ($this->board[$r][$c]->suspectId === $suspectId) {
                    return ['row' => $r, 'col' => $c];
                }
            }
        }

        return null;
    }

    public function areAdjacent(string $a, string $b): bool
    {
        $pa = $this->findPosition($a);
        $pb = $this->findPosition($b);
        if ($pa === null || $pb === null) {
            return false;
        }
        $dr = abs($pa['row'] - $pb['row']);
        $dc = abs($pa['col'] - $pb['col']);

        // Соседи по стороне (не по диагонали), включая «себя» (0,0)
        return ($dr + $dc) === 1 || ($dr === 0 && $dc === 0);
    }

    public function isOrthogonalNeighbor(string $a, string $b): bool
    {
        $pa = $this->findPosition($a);
        $pb = $this->findPosition($b);
        if ($pa === null || $pb === null) {
            return false;
        }
        $dr = abs($pa['row'] - $pb['row']);
        $dc = abs($pa['col'] - $pb['col']);

        return ($dr + $dc) === 1;
    }

    /**
     * @return array{ok: bool, error?: string, events?: list<array<string, mixed>>}
     */
    public function performAction(string $playerId, array $payload): array
    {
        if ($this->finished) {
            return ['ok' => false, 'error' => 'Игра уже окончена'];
        }
        if ($this->getCurrentPlayerId() !== $playerId) {
            return ['ok' => false, 'error' => 'Сейчас не ваш ход'];
        }

        $action = $payload['action'] ?? '';
        $events = [];

        try {
            match ($action) {
                'catch' => $events = $this->actionCatch($playerId, (string) ($payload['targetId'] ?? '')),
                'interrogate' => $events = $this->actionInterrogate($playerId, (string) ($payload['targetId'] ?? '')),
                'shift' => $events = $this->actionShift(
                    (string) ($payload['axis'] ?? ''),
                    (int) ($payload['index'] ?? -1),
                    (string) ($payload['direction'] ?? '')
                ),
                'update' => $events = $this->actionUpdate(),
                default => throw new \InvalidArgumentException('Неизвестное действие'),
            };
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (!$this->finished) {
            $this->advanceTurn();
        }

        return ['ok' => true, 'events' => $events];
    }

    /** @return list<array<string, mixed>> */
    private function actionCatch(string $playerId, string $targetId): array
    {
        $actor = $this->players[$playerId];
        $actorIdentity = $actor['identityId'];
        if ($actorIdentity === null) {
            throw new \RuntimeException('Нет тайной личности');
        }
        if ($actorIdentity === $targetId || !$this->isOrthogonalNeighbor($actorIdentity, $targetId)) {
            throw new \RuntimeException('Выберите соседнего подозреваемого');
        }

        $pos = $this->findPosition($targetId);
        if ($pos === null) {
            throw new \RuntimeException('Подозреваемый не на поле');
        }
        $cell = $this->board[$pos['row']][$pos['col']];
        if (!$cell->alive) {
            throw new \RuntimeException('Этот подозреваемый уже погиб');
        }

        $events = [];
        $caughtPlayerId = null;
        foreach ($this->players as $pid => $p) {
            if ($p['identityId'] === $targetId) {
                // В командном режиме нельзя поймать своего
                if ($this->isTeamMode()
                    && $actor['team'] !== null
                    && $p['team'] === $actor['team']
                ) {
                    throw new \RuntimeException('Нельзя поймать союзника');
                }
                $caughtPlayerId = $pid;
                break;
            }
        }

        $targetName = $cell->name;

        if ($caughtPlayerId === null) {
            $this->log[] = [
                'type' => 'catch_miss',
                'message' => sprintf('%s спрашивает: «Кто-то из вас %s?» — никто.', $actor['name'], $targetName),
            ];
            $events[] = ['type' => 'catch_miss', 'targetId' => $targetId, 'targetName' => $targetName];

            return $events;
        }

        // Поймали шпиона
        $cell->alive = false;
        $trophy = ['suspectId' => $targetId, 'name' => $targetName];
        $this->players[$playerId]['trophies'][] = $trophy;

        $caughtName = $this->players[$caughtPlayerId]['name'];
        $this->assignNewIdentity($caughtPlayerId);

        $this->log[] = [
            'type' => 'catch_hit',
            'message' => sprintf(
                '%s поймал шпиона %s (%s)! Трофей получен.',
                $actor['name'],
                $caughtName,
                $targetName
            ),
        ];
        $events[] = [
            'type' => 'catch_hit',
            'targetId' => $targetId,
            'targetName' => $targetName,
            'caughtPlayerId' => $caughtPlayerId,
            'caughtPlayerName' => $caughtName,
            'hunterId' => $playerId,
        ];

        $this->checkVictory($playerId);

        return $events;
    }

    /** @return list<array<string, mixed>> */
    private function actionInterrogate(string $playerId, string $targetId): array
    {
        $actor = $this->players[$playerId];
        $actorIdentity = $actor['identityId'];
        if ($actorIdentity === null) {
            throw new \RuntimeException('Нет тайной личности');
        }

        // Цель — соседняя или сам (допрос себя разрешён)
        $isSelf = $actorIdentity === $targetId;
        if (!$isSelf && !$this->isOrthogonalNeighbor($actorIdentity, $targetId)) {
            throw new \RuntimeException('Цель допроса должна быть соседней (или вы сами)');
        }

        $pos = $this->findPosition($targetId);
        if ($pos === null) {
            throw new \RuntimeException('Подозреваемый не на поле');
        }

        $raising = [];
        foreach ($this->players as $pid => $p) {
            $identity = $p['identityId'];
            if ($identity === null) {
                continue;
            }
            // Поднимают руку: те, кто рядом с целью, И тот, чья личность — цель
            if ($identity === $targetId || $this->isOrthogonalNeighbor($identity, $targetId)) {
                $raising[] = [
                    'playerId' => $pid,
                    'playerName' => $p['name'],
                ];
            }
        }

        $targetName = $this->board[$pos['row']][$pos['col']]->name;
        $names = array_map(static fn (array $r) => $r['playerName'], $raising);
        $this->log[] = [
            'type' => 'interrogate',
            'message' => sprintf(
                '%s допрашивает %s. Руку подняли: %s.',
                $actor['name'],
                $targetName,
                $names === [] ? 'никто' : implode(', ', $names)
            ),
        ];

        return [[
            'type' => 'interrogate',
            'targetId' => $targetId,
            'targetName' => $targetName,
            'raisingHands' => $raising,
        ]];
    }

    /** @return list<array<string, mixed>> */
    private function actionShift(string $axis, int $index, string $direction): array
    {
        if (!in_array($axis, ['row', 'col'], true)) {
            throw new \InvalidArgumentException('axis: row|col');
        }
        if ($index < 0 || $index >= $this->size) {
            throw new \InvalidArgumentException('Неверный индекс ряда');
        }
        if (!in_array($direction, ['forward', 'backward'], true)) {
            throw new \InvalidArgumentException('direction: forward|backward');
        }

        // Запрет отмены предыдущего сдвига
        if ($this->lastShift !== null
            && $this->lastShift['axis'] === $axis
            && $this->lastShift['index'] === $index
            && $this->lastShift['direction'] !== $direction
        ) {
            throw new \RuntimeException('Нельзя сразу отменить предыдущий сдвиг');
        }

        if ($axis === 'row') {
            $row = $this->board[$index];
            if ($direction === 'forward') {
                // вправо
                $last = array_pop($row);
                array_unshift($row, $last);
            } else {
                // влево
                $first = array_shift($row);
                $row[] = $first;
            }
            $this->board[$index] = array_values($row);
        } else {
            $col = [];
            for ($r = 0; $r < $this->size; ++$r) {
                $col[] = $this->board[$r][$index];
            }
            if ($direction === 'forward') {
                // вниз
                $last = array_pop($col);
                array_unshift($col, $last);
            } else {
                // вверх
                $first = array_shift($col);
                $col[] = $first;
            }
            for ($r = 0; $r < $this->size; ++$r) {
                $this->board[$r][$index] = $col[$r];
            }
        }

        $this->lastShift = [
            'axis' => $axis,
            'index' => $index,
            'direction' => $direction,
        ];

        $label = $axis === 'row'
            ? sprintf('ряд %d %s', $index + 1, $direction === 'forward' ? 'вправо' : 'влево')
            : sprintf('столбец %d %s', $index + 1, $direction === 'forward' ? 'вниз' : 'вверх');

        $this->log[] = [
            'type' => 'shift',
            'message' => sprintf('Сдвиг: %s.', $label),
        ];

        return [['type' => 'shift', 'axis' => $axis, 'index' => $index, 'direction' => $direction]];
    }

    /** @return list<array<string, mixed>> */
    private function actionUpdate(): array
    {
        $removed = 0;
        // Можно обновлять, пока в каждом ряду/столбце есть погибший
        $guard = 0;
        while ($guard++ < 20) {
            $deadInEveryRow = true;
            for ($r = 0; $r < $this->size; ++$r) {
                $hasDead = false;
                for ($c = 0; $c < $this->size; ++$c) {
                    if (!$this->board[$r][$c]->alive) {
                        $hasDead = true;
                        break;
                    }
                }
                if (!$hasDead) {
                    $deadInEveryRow = false;
                    break;
                }
            }

            $deadInEveryCol = true;
            for ($c = 0; $c < $this->size; ++$c) {
                $hasDead = false;
                for ($r = 0; $r < $this->size; ++$r) {
                    if (!$this->board[$r][$c]->alive) {
                        $hasDead = true;
                        break;
                    }
                }
                if (!$hasDead) {
                    $deadInEveryCol = false;
                    break;
                }
            }

            if (!$deadInEveryRow && !$deadInEveryCol) {
                break;
            }

            if ($deadInEveryRow) {
                // Убрать погибших и сомкнуть по вертикали (в каждом столбце сдвинуть живых вверх)
                for ($c = 0; $c < $this->size; ++$c) {
                    $alive = [];
                    $dead = [];
                    for ($r = 0; $r < $this->size; ++$r) {
                        if ($this->board[$r][$c]->alive) {
                            $alive[] = $this->board[$r][$c];
                        } else {
                            $dead[] = $this->board[$r][$c];
                            ++$removed;
                        }
                    }
                    // Сомкнуть: живые сверху, пустые/мёртвые убираем из игры — заменяем null? 
                    // По правилам карты убираются и поле смыкается. Для сетки фиксированного размера
                    // типичная реализация: живые сдвигаются к центру/верху, мёртвые удаляются
                    // и поле уменьшается. Упрощение: сдвигаем живых вверх, мёртвые в конец как «пусто».
                    $merged = array_merge($alive, $dead);
                    for ($r = 0; $r < $this->size; ++$r) {
                        $this->board[$r][$c] = $merged[$r];
                    }
                }
                // После смыкания мёртвые внизу — убираем строки, где все мертвы, сжимая поле
                $this->compactBoard();
            } elseif ($deadInEveryCol) {
                for ($r = 0; $r < $this->size; ++$r) {
                    $alive = [];
                    $dead = [];
                    for ($c = 0; $c < $this->size; ++$c) {
                        if ($this->board[$r][$c]->alive) {
                            $alive[] = $this->board[$r][$c];
                        } else {
                            $dead[] = $this->board[$r][$c];
                            ++$removed;
                        }
                    }
                    $merged = array_merge($alive, $dead);
                    for ($c = 0; $c < $this->size; ++$c) {
                        $this->board[$r][$c] = $merged[$c];
                    }
                }
                $this->compactBoard();
            }
        }

        if ($removed === 0) {
            throw new \RuntimeException(
                'Обновление недоступно: нужен хотя бы один погибший в каждом ряду или в каждом столбце'
            );
        }

        $this->lastShift = null;
        $this->log[] = [
            'type' => 'update',
            'message' => 'Игровое поле обновлено (погибшие убраны, поле сомкнуто).',
        ];

        return [['type' => 'update', 'removed' => $removed, 'size' => $this->size]];
    }

    private function compactBoard(): void
    {
        // Удаляем полностью мёртвые ряды и столбцы с края, уменьшая size
        // Упрощённая компактификация: оставляем только клетки с живыми + минимальный квадрат
        $alivePositions = [];
        for ($r = 0; $r < $this->size; ++$r) {
            for ($c = 0; $c < $this->size; ++$c) {
                if ($this->board[$r][$c]->alive) {
                    $alivePositions[] = [$r, $c];
                }
            }
        }
        if ($alivePositions === []) {
            return;
        }

        $minR = min(array_column($alivePositions, 0));
        $maxR = max(array_column($alivePositions, 0));
        $minC = min(array_column($alivePositions, 1));
        $maxC = max(array_column($alivePositions, 1));

        $newBoard = [];
        for ($r = $minR; $r <= $maxR; ++$r) {
            $row = [];
            for ($c = $minC; $c <= $maxC; ++$c) {
                $cell = $this->board[$r][$c];
                if ($cell->alive) {
                    $row[] = $cell;
                }
            }
            if ($row !== []) {
                $newBoard[] = $row;
            }
        }

        // Выровнять в прямоугольник / квадрат — дополнять нельзя картами.
        // Берём как есть нерегулярную сетку → нормализуем по макс. ширине, выкидывая мёртвых.
        $maxWidth = 0;
        foreach ($newBoard as $row) {
            $maxWidth = max($maxWidth, count($row));
        }
        if ($maxWidth === 0) {
            return;
        }

        $normalized = [];
        foreach ($newBoard as $row) {
            if (count($row) === $maxWidth) {
                $normalized[] = $row;
            } else {
                // Ряд короче — пропускаем неполные после фильтра; оставим как есть, pad нет
                // Для простоты: только если все ряды одной длины
                $normalized[] = $row;
            }
        }

        // Если ряды разной длины — оставляем прежнюю доску без сжатия краёв мёртвых-only
        $lengths = array_map('count', $normalized);
        if (count(array_unique($lengths)) === 1 && $lengths[0] > 0) {
            $this->board = $normalized;
            $this->size = count($this->board);
        }
    }

    private function assignNewIdentity(string $playerId): void
    {
        // Новая личность только из живых на поле и не занятых другими
        $taken = [];
        foreach ($this->players as $p) {
            if ($p['identityId'] !== null) {
                $taken[$p['identityId']] = true;
            }
        }

        while ($this->evidenceDeck !== []) {
            $next = array_shift($this->evidenceDeck);
            $pos = $this->findPosition($next);
            if ($pos === null) {
                continue;
            }
            if (!$this->board[$pos['row']][$pos['col']]->alive) {
                continue;
            }
            if (isset($taken[$next])) {
                continue;
            }
            $this->players[$playerId]['identityId'] = $next;

            return;
        }

        // Колода пуста — ищем любого живого свободного на поле
        for ($r = 0; $r < $this->size; ++$r) {
            for ($c = 0; $c < $this->size; ++$c) {
                $cell = $this->board[$r][$c];
                if ($cell->alive && !isset($taken[$cell->suspectId])) {
                    $this->players[$playerId]['identityId'] = $cell->suspectId;

                    return;
                }
            }
        }

        $this->players[$playerId]['identityId'] = null;
    }

    private function checkVictory(string $hunterId): void
    {
        $count = count($this->players);
        $need = self::trophiesToWin($count);

        if ($this->isTeamMode()) {
            $team = $this->players[$hunterId]['team'];
            $teamTrophies = 0;
            $teamMembers = [];
            foreach ($this->players as $p) {
                if ($p['team'] === $team) {
                    $teamTrophies += count($p['trophies']);
                    $teamMembers[] = $p['id'];
                }
            }
            if ($teamTrophies >= $need) {
                $this->finished = true;
                $this->winnerIds = $teamMembers;
                $this->winnerLabel = sprintf('Команда %d', ($team ?? 0) + 1);
                $this->log[] = [
                    'type' => 'victory',
                    'message' => sprintf('Победа! %s собрала %d трофеев.', $this->winnerLabel, $teamTrophies),
                ];
            }
        } else {
            $trophies = count($this->players[$hunterId]['trophies']);
            if ($trophies >= $need) {
                $this->finished = true;
                $this->winnerIds = [$hunterId];
                $this->winnerLabel = $this->players[$hunterId]['name'];
                $this->log[] = [
                    'type' => 'victory',
                    'message' => sprintf('Победа! %s собрал(а) %d трофеев.', $this->winnerLabel, $trophies),
                ];
            }
        }
    }

    private function advanceTurn(): void
    {
        $this->currentTurnIndex = ($this->currentTurnIndex + 1) % count($this->turnOrder);
        // Запрет отмены действует один ход: после следующего действия сдвиг можно отменить.
        if ($this->lastShift !== null) {
            if (($this->lastShift['pendingClear'] ?? false) === true) {
                $this->lastShift = null;
            } else {
                $this->lastShift['pendingClear'] = true;
            }
        }
    }

    /**
     * Публичное состояние + приватные поля для конкретного игрока.
     *
     * @return array<string, mixed>
     */
    public function toClientState(string $viewerId): array
    {
        $board = [];
        for ($r = 0; $r < $this->size; ++$r) {
            $row = [];
            for ($c = 0; $c < $this->size; ++$c) {
                $row[] = $this->board[$r][$c]->toArray();
            }
            $board[] = $row;
        }

        $publicPlayers = [];
        foreach ($this->players as $p) {
            $entry = [
                'id' => $p['id'],
                'name' => $p['name'],
                'team' => $p['team'],
                'trophyCount' => count($p['trophies']),
                'trophies' => $p['trophies'],
            ];
            if ($p['id'] === $viewerId) {
                $entry['identityId'] = $p['identityId'];
                $entry['identityName'] = $this->nameOf($p['identityId']);
            }
            $publicPlayers[] = $entry;
        }

        $adjacent = [];
        $myIdentity = $this->players[$viewerId]['identityId'] ?? null;
        if ($myIdentity !== null) {
            $pos = $this->findPosition($myIdentity);
            if ($pos !== null) {
                foreach ([[0, 1], [0, -1], [1, 0], [-1, 0], [0, 0]] as [$dr, $dc]) {
                    $rr = $pos['row'] + $dr;
                    $cc = $pos['col'] + $dc;
                    if ($rr >= 0 && $rr < $this->size && $cc >= 0 && $cc < $this->size) {
                        $adjacent[] = $this->board[$rr][$cc]->suspectId;
                    }
                }
            }
        }

        return [
            'id' => $this->id,
            'lobbyId' => $this->lobbyId,
            'artPack' => $this->artPack,
            'size' => $this->size,
            'board' => $board,
            'players' => $publicPlayers,
            'currentPlayerId' => $this->getCurrentPlayerId(),
            'turnOrder' => $this->turnOrder,
            'finished' => $this->finished,
            'winnerIds' => $this->winnerIds,
            'winnerLabel' => $this->winnerLabel,
            'trophiesToWin' => self::trophiesToWin(count($this->players)),
            'teamMode' => $this->isTeamMode(),
            'log' => array_slice($this->log, -30),
            'myAdjacentIds' => $adjacent,
            'canUpdate' => $this->canUpdate(),
            'deckRemaining' => count($this->evidenceDeck),
        ];
    }

    private function nameOf(?string $suspectId): ?string
    {
        if ($suspectId === null) {
            return null;
        }
        $pos = $this->findPosition($suspectId);
        if ($pos === null) {
            return $suspectId;
        }

        return $this->board[$pos['row']][$pos['col']]->name;
    }

    private function canUpdate(): bool
    {
        $deadInEveryRow = true;
        for ($r = 0; $r < $this->size; ++$r) {
            $hasDead = false;
            for ($c = 0; $c < $this->size; ++$c) {
                if (!$this->board[$r][$c]->alive) {
                    $hasDead = true;
                    break;
                }
            }
            if (!$hasDead) {
                $deadInEveryRow = false;
                break;
            }
        }
        $deadInEveryCol = true;
        for ($c = 0; $c < $this->size; ++$c) {
            $hasDead = false;
            for ($r = 0; $r < $this->size; ++$r) {
                if (!$this->board[$r][$c]->alive) {
                    $hasDead = true;
                    break;
                }
            }
            if (!$hasDead) {
                $deadInEveryCol = false;
                break;
            }
        }

        return $deadInEveryRow || $deadInEveryCol;
    }
}
