<?php

namespace App\Http\Controllers;

use App\Application\Multiplayer\GameEventRecorder;
use App\Application\Multiplayer\RevisionGuard;
use App\Domain\Multiplayer\GameStatus;
use App\Http\Requests\Game\RevisionRequest;
use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GameStateController extends Controller
{
    public function pause(RevisionRequest $request, Game $game, RevisionGuard $revisions, GameEventRecorder $events): GameResource
    {
        $this->authorize('pause', $game);
        $this->transition($request, $game, GameStatus::Active, GameStatus::Paused, 'GamePaused', $revisions, $events);

        return new GameResource($game->fresh()->load(['scenario', 'memberships.user', 'memberships.team', 'memberships.roles', 'turnState']));
    }

    public function resume(RevisionRequest $request, Game $game, RevisionGuard $revisions, GameEventRecorder $events): GameResource
    {
        $this->authorize('pause', $game);
        $this->transition($request, $game, GameStatus::Paused, GameStatus::Active, 'GameResumed', $revisions, $events);

        return new GameResource($game->fresh()->load(['scenario', 'memberships.user', 'memberships.team', 'memberships.roles', 'turnState']));
    }

    private function transition(RevisionRequest $request, Game $game, GameStatus $from, GameStatus $to, string $event, RevisionGuard $revisions, GameEventRecorder $events): void
    {
        DB::transaction(function () use ($request, $game, $from, $to, $event, $revisions, $events): void {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $revisions->assert($locked, $request->integer('revision'));
            if ($locked->status !== $from) {
                throw ValidationException::withMessages(['game' => "Game must be {$from->value}."]);
            }
            $locked->status = $to;
            $events->record($locked, $event, $request->user());
            $revisions->bump($locked);
        });
    }
}
