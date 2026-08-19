<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GameStatusController extends Controller
{
    public function __invoke(Request $request, Game $game): JsonResponse
    {
        $this->authorize('view', $game);
        $game->load(['turnState', 'decisions' => fn ($query) => $query->where('status', 'PENDING'), 'decisions.team']);

        return response()->json(['data' => [
            'game' => $game->id, 'status' => $game->status->value, 'revision' => $game->revision,
            'turn' => $game->turnState?->turn, 'operation_stage' => $game->turnState?->operation_stage, 'phase' => $game->turnState?->phase,
            'waiting_for' => $game->decisions->map(fn ($decision) => [
                'decision_id' => $decision->id, 'type' => $decision->type,
                'team' => $decision->team?->code?->value, 'required_role' => $decision->required_role_code,
            ])->values(),
        ]]);
    }
}
