<?php

namespace App\Application\Multiplayer;

use App\Domain\Multiplayer\GameStatus;
use App\Models\Game;
use App\Models\GameInvitation;
use App\Models\GameMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptInvitation
{
    public function __construct(private readonly GameEventRecorder $events, private readonly RevisionGuard $revisions) {}

    public function handle(string $token, User $user, ?string $teamCode): GameMembership
    {
        return DB::transaction(function () use ($token, $user, $teamCode): GameMembership {
            $invite = GameInvitation::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $invite || $invite->accepted_at || $invite->declined_at || $invite->expires_at->isPast()) {
                throw ValidationException::withMessages(['token' => 'Invitation is invalid or expired.']);
            }
            if ($invite->invited_email && strcasecmp($invite->invited_email, $user->email) !== 0) {
                throw ValidationException::withMessages(['token' => 'This invitation belongs to another email address.']);
            }
            $game = Game::query()->lockForUpdate()->findOrFail($invite->game_id);
            if ($game->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages(['game' => 'The lobby is closed.']);
            }
            if ($game->memberships()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['game' => 'You already joined this game.']);
            }
            $selectedTeam = $invite->team_code ?? $teamCode;
            $team = $selectedTeam ? $game->teams()->where('code', $selectedTeam)->first() : null;
            if ($invite->privilege->value === 'PLAYER' && ! $team) {
                throw ValidationException::withMessages(['team' => 'A player must select a team.']);
            }
            $membership = GameMembership::create([
                'game_id' => $game->id, 'user_id' => $user->id, 'team_id' => $team?->id, 'privilege' => $invite->privilege,
            ]);
            $invite->update(['accepted_at' => now(), 'accepted_by' => $user->id]);
            $this->events->record($game, 'PlayerJoinedGame', $user, ['user_id' => $user->id, 'team' => $team?->code->value, 'via_invitation' => true]);
            $this->revisions->bump($game);

            return $membership->load(['game', 'user', 'team', 'roles']);
        });
    }
}
