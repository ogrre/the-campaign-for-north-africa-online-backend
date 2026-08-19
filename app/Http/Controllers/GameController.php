<?php

namespace App\Http\Controllers;

use App\Application\Multiplayer\CreateGame;
use App\Application\Multiplayer\GameEventRecorder;
use App\Application\Multiplayer\RevisionGuard;
use App\Domain\Multiplayer\TeamCode;
use App\Http\Requests\Game\CreateGameRequest;
use App\Http\Requests\Game\UpdateGameRequest;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\ScenarioDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

final class GameController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $games = Game::query()->where(fn ($q) => $q->where('creator_id', $request->user()->id)->orWhereHas('memberships', fn ($m) => $m->where('user_id', $request->user()->id)))
            ->with(['scenario', 'memberships.user', 'memberships.team', 'memberships.roles', 'turnState'])->latest()->get();

        return GameResource::collection($games);
    }

    public function store(CreateGameRequest $request, CreateGame $command): JsonResponse
    {
        $scenario = ScenarioDefinition::findOrFail($request->validated('scenario_id'));

        return (new GameResource($command->handle($request->user(), $scenario, $request->validated('name'), TeamCode::from($request->validated('team')))))
            ->response()->setStatusCode(201);
    }

    public function show(Request $request, Game $game): GameResource
    {
        $this->authorize('view', $game);

        return new GameResource($game->load(['scenario', 'memberships.user', 'memberships.team', 'memberships.roles', 'turnState']));
    }

    public function update(UpdateGameRequest $request, Game $game, RevisionGuard $revisions, GameEventRecorder $events): GameResource
    {
        $this->authorize('updateLobby', $game);
        DB::transaction(function () use ($request, $game, $revisions, $events): void {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $revisions->assert($locked, $request->integer('revision'));
            $locked->name = $request->validated('name');
            $events->record($locked, 'GameRenamed', $request->user(), ['name' => $locked->name]);
            $revisions->bump($locked);
        });

        return new GameResource($game->fresh()->load(['scenario', 'memberships.user', 'memberships.team', 'memberships.roles', 'turnState']));
    }
}
