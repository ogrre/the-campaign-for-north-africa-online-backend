<?php

namespace App\Domain\Multiplayer;

enum GameStatus: string
{
    case Lobby = 'lobby';
    case Active = 'active';
    case Paused = 'paused';
    case Finished = 'finished';
    case Abandoned = 'abandoned';
}
