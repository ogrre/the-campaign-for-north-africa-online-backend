<?php

namespace App\Domain\Multiplayer;

enum MembershipPrivilege: string
{
    case Player = 'PLAYER';
    case Observer = 'OBSERVER';
    case Gamemaster = 'GAMEMASTER';
}
