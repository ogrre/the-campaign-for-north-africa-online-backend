<?php

namespace App\Domain\Multiplayer;

enum RoleCode: string
{
    case CommanderInChief = 'COMMANDER_IN_CHIEF';
    case LogisticsCommander = 'LOGISTICS_COMMANDER';
    case RearAreaCommander = 'REAR_AREA_COMMANDER';
    case AirCommander = 'AIR_COMMANDER';
    case FrontLineCommander = 'FRONT_LINE_COMMANDER';
}
