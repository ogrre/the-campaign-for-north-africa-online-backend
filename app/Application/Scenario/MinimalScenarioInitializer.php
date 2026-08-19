<?php

namespace App\Application\Scenario;

use App\Models\Game;
use App\Models\GameTurnState;
use App\Models\ScenarioDefinition;
use Illuminate\Validation\ValidationException;

final class MinimalScenarioInitializer implements ScenarioInitializer
{
    public function initialize(Game $game, ScenarioDefinition $scenario): void
    {
        if ($scenario->initial_turn === null || $scenario->initial_operation_stage === null) {
            throw ValidationException::withMessages(['scenario' => 'The scenario has no verified initial turn state.']);
        }

        GameTurnState::create([
            'game_id' => $game->id,
            'turn' => $scenario->initial_turn,
            'operation_stage' => $scenario->initial_operation_stage,
            'phase' => 'AWAITING_INITIAL_DECISIONS',
        ]);
    }
}
