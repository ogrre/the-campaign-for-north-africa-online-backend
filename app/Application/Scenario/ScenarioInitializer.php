<?php

namespace App\Application\Scenario;

use App\Models\Game;
use App\Models\ScenarioDefinition;

interface ScenarioInitializer
{
    public function initialize(Game $game, ScenarioDefinition $scenario): void;
}
