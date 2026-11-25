<?php

declare(strict_types=1);

namespace App\Domain\Lobby;

enum LobbyStatus: string
{
    case Gathering = 'gathering';
    case Playing = 'playing';
    case Finished = 'finished';
}
