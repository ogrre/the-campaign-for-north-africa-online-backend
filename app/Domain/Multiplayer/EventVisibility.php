<?php

namespace App\Domain\Multiplayer;

enum EventVisibility: string
{
    case Public = 'PUBLIC';
    case PrivateTeam = 'PRIVATE_TEAM';
    case PrivatePlayer = 'PRIVATE_PLAYER';
    case PublicAfterResolution = 'PUBLIC_AFTER_RESOLUTION';
    case GamemasterOnly = 'GAMEMASTER_ONLY';
}
