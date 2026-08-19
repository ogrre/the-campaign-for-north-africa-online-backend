<?php

namespace App\Http\Controllers;

use App\Application\Multiplayer\AssignRole;
use App\Application\Multiplayer\GameEventRecorder;
use App\Application\Multiplayer\RevisionGuard;
use App\Domain\Multiplayer\GameStatus;
use App\Http\Requests\Game\AssignRoleRequest;
use App\Http\Requests\Game\RevisionRequest;
use App\Http\Requests\Game\UpdateMembershipRequest;
use App\Http\Resources\MembershipResource;
use App\Models\Game;
use App\Models\GameMembership;
use App\Models\GameRoleDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GamePlayerController extends Controller
{
    public function index(Request $request, Game $game): AnonymousResourceCollection
    {
        $this->authorize('view', $game);

        return MembershipResource::collection($game->memberships()->with(['user', 'team', 'roles'])->get());
    }

    public function update(UpdateMembershipRequest $request, Game $game, GameMembership $player, RevisionGuard $revisions, GameEventRecorder $events): MembershipResource
    {
        $this->authorize('updateLobby', $game);
        $this->ensureMemberOf($player, $game);
        DB::transaction(function () use ($request, $game, $player, $revisions, $events): void {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $revisions->assert($locked, $request->integer('revision'));
            if ($locked->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages(['game' => 'The lobby is closed.']);
            }
            if ($request->has('team')) {
                $player->team_id = $request->validated('team') ? $locked->teams()->where('code', $request->validated('team'))->value('id') : null;
            }
            if ($request->has('privilege')) {
                $player->privilege = $request->validated('privilege');
            }
            $player->save();
            $events->record($locked, 'PlayerMembershipUpdated', $request->user(), ['user_id' => $player->user_id, 'team' => $request->validated('team')]);
            $revisions->bump($locked);
        });

        return new MembershipResource($player->fresh()->load(['user', 'team', 'roles']));
    }

    public function destroy(RevisionRequest $request, Game $game, GameMembership $player, RevisionGuard $revisions, GameEventRecorder $events): JsonResponse
    {
        $this->authorize('updateLobby', $game);
        $this->ensureMemberOf($player, $game);
        if ($player->user_id === $game->creator_id) {
            throw ValidationException::withMessages(['player' => 'The creator cannot be removed.']);
        }
        DB::transaction(function () use ($request, $game, $player, $revisions, $events): void {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $revisions->assert($locked, $request->integer('revision'));
            $userId = $player->user_id;
            $player->delete();
            $events->record($locked, 'PlayerRemovedFromGame', $request->user(), ['user_id' => $userId]);
            $revisions->bump($locked);
        });

        return response()->json([], 204);
    }

    public function addRole(AssignRoleRequest $request, Game $game, GameMembership $player, AssignRole $command): JsonResponse
    {
        $this->authorize('assignRole', $game);
        $this->ensureMemberOf($player, $game);
        $role = GameRoleDefinition::findOrFail($request->validated('role'));
        $command->add($game, $player, $role, $request->user(), $request->integer('revision'));

        return response()->json(['data' => new MembershipResource($player->fresh()->load(['user', 'team', 'roles']))], 201);
    }

    public function removeRole(RevisionRequest $request, Game $game, GameMembership $player, GameRoleDefinition $role, AssignRole $command): JsonResponse
    {
        $this->authorize('assignRole', $game);
        $this->ensureMemberOf($player, $game);
        $command->remove($game, $player, $role, $request->user(), $request->integer('revision'));

        return response()->json([], 204);
    }

    private function ensureMemberOf(GameMembership $player, Game $game): void
    {
        abort_unless($player->game_id === $game->id, 404);
    }
}
