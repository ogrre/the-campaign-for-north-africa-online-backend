<?php

namespace App\Http\Controllers;

use App\Domain\Multiplayer\MembershipPrivilege;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GameEventController extends Controller
{
    public function __invoke(Request $request, Game $game): JsonResponse
    {
        $this->authorize('view', $game);
        $membership = $game->memberships()->where('user_id', $request->user()->id)->first();
        $isGamemaster = $membership?->privilege === MembershipPrivilege::Gamemaster;
        $events = $game->events()->where(function ($query) use ($membership, $isGamemaster): void {
            $query->where('visibility', 'PUBLIC');
            if ($membership?->team_id) {
                $query->orWhere(fn ($team) => $team->where('visibility', 'PRIVATE_TEAM')->where('team_id', $membership->team_id));
            }
            if ($isGamemaster) {
                $query->orWhereIn('visibility', ['GAMEMASTER_ONLY', 'PUBLIC_AFTER_RESOLUTION']);
            }
        })->orderBy('sequence')->get(['id', 'sequence', 'type', 'actor_user_id', 'team_id', 'visibility', 'payload', 'created_at']);

        return response()->json(['data' => $events]);
    }
}
