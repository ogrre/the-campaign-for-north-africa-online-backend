<?php

namespace App\Application\Multiplayer;

use App\Domain\Multiplayer\GameStatus;
use App\Domain\Multiplayer\MembershipPrivilege;
use App\Domain\Multiplayer\TeamCode;
use App\Models\Game;
use App\Models\GameMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class JoinGame
{
    public function __construct(private readonly GameEventRecorder $events, private readonly RevisionGuard $revisions) {}

    public function handle(Game $game, User $user, TeamCode $teamCode, int $expectedRevision): GameMembership
    {
        return DB::transaction(function () use ($game, $user, $teamCode, $expectedRevision): GameMembership {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $this->revisions->assert($locked, $expectedRevision);
            if ($locked->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages(['game' => 'The lobby is closed.']);
            }
            if ($locked->memberships()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['game' => 'You already joined this game.']);
            }
            $team = $locked->teams()->where('code', $teamCode)->firstOrFail();
            $membership = GameMembership::create([
                'game_id' => $locked->id, 'user_id' => $user->id, 'team_id' => $team->id,
                'privilege' => MembershipPrivilege::Player,
            ]);
            $this->events->record($locked, 'PlayerJoinedGame', $user, ['user_id' => $user->id, 'team' => $teamCode->value]);
            $this->revisions->bump($locked);

            return $membership->load(['user', 'team', 'roles']);
        });
    }
}
