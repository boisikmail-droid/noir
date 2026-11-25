<?php

declare(strict_types=1);

namespace App\Domain\SpyGames;

final class BoardCell
{
    public function __construct(
        public readonly string $suspectId,
        public readonly string $name,
        public bool $alive = true,
    ) {
    }

    /** @return array{suspectId: string, name: string, alive: bool} */
    public function toArray(): array
    {
        return [
            'suspectId' => $this->suspectId,
            'name' => $this->name,
            'alive' => $this->alive,
        ];
    }
}
