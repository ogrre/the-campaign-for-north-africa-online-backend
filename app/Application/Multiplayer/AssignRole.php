<?php

namespace App\Application\Multiplayer;

use App\Domain\Multiplayer\GameStatus;
use App\Models\Game;
use App\Models\GameMembership;
use App\Models\GameRoleDefinition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssignRole
{
    public function __construct(private readonly GameEventRecorder $events, private readonly RevisionGuard $revisions) {}

    public function add(Game $game, GameMembership $membership, GameRoleDefinition $role, User $actor, int $revision): void
    {
        DB::transaction(function () use ($game, $membership, $role, $actor, $revision): void {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $this->revisions->assert($locked, $revision);
            if ($locked->status !== GameStatus::Lobby || $membership->game_id !== $locked->id || $membership->team_id === null) {
                throw ValidationException::withMessages(['role' => 'Roles can only be changed for team players in an open lobby.']);
            }
            $membership->roles()->syncWithoutDetaching([$role->code]);
            $this->events->record($locked, 'PlayerAssignedRole', $actor, ['user_id' => $membership->user_id, 'role' => $role->code]);
            $this->revisions->bump($locked);
        });
    }

    public function remove(Game $game, GameMembership $membership, GameRoleDefinition $role, User $actor, int $revision): void
    {
        DB::transaction(function () use ($game, $membership, $role, $actor, $revision): void {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $this->revisions->assert($locked, $revision);
            if ($locked->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages(['game' => 'The lobby is closed.']);
            }
            $membership->roles()->detach($role->code);
            $this->events->record($locked, 'PlayerRoleRemoved', $actor, ['user_id' => $membership->user_id, 'role' => $role->code]);
            $this->revisions->bump($locked);
        });
    }
}
