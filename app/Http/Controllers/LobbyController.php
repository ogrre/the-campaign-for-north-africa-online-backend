<?php

namespace App\Http\Controllers;

use App\Application\Multiplayer\GameEventRecorder;
use App\Application\Multiplayer\JoinGame;
use App\Application\Multiplayer\RevisionGuard;
use App\Application\Multiplayer\StartGame;
use App\Domain\Multiplayer\TeamCode;
use App\Http\Requests\Game\JoinGameRequest;
use App\Http\Requests\Game\RevisionRequest;
use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class LobbyController extends Controller
{
    public function join(JoinGameRequest $request, Game $game, JoinGame $command): JsonResponse
    {
        $membership = $command->handle($game, $request->user(), TeamCode::from($request->validated('team')), $request->integer('revision'));

        return response()->json(['data' => $membership], 201);
    }

    public function leave(RevisionRequest $request, Game $game, RevisionGuard $revisions, GameEventRecorder $events): JsonResponse
    {
        $this->authorize('leave', $game);
        DB::transaction(function () use ($request, $game, $revisions, $events): void {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $revisions->assert($locked, $request->integer('revision'));
            $membership = $locked->memberships()->where('user_id', $request->user()->id)->firstOrFail();
            $membership->delete();
            $events->record($locked, 'PlayerLeftGame', $request->user(), ['user_id' => $request->user()->id]);
            $revisions->bump($locked);
        });

        return response()->json([], 204);
    }

    public function start(RevisionRequest $request, Game $game, StartGame $command): GameResource
    {
        $this->authorize('start', $game);

        return new GameResource($command->handle($game, $request->integer('revision')));
    }
}
