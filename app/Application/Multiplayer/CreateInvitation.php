<?php

namespace App\Application\Multiplayer;

use App\Domain\Multiplayer\GameStatus;
use App\Models\Game;
use App\Models\GameInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateInvitation
{
    public function __construct(private readonly RevisionGuard $revisions) {}

    /**
     * @param  array{revision: int, email?: string|null, team?: string|null, privilege?: string|null}  $attributes
     * @return array{GameInvitation, string}
     */
    public function handle(Game $game, User $actor, array $attributes): array
    {
        return DB::transaction(function () use ($game, $actor, $attributes): array {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $this->revisions->assert($locked, $attributes['revision']);
            if ($locked->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages(['game' => 'The lobby is closed.']);
            }
            $token = Str::random(64);
            $invite = GameInvitation::create([
                'game_id' => $locked->id,
                'invited_by' => $actor->id,
                'token_hash' => hash('sha256', $token),
                'invited_email' => $attributes['email'] ?? null,
                'team_code' => $attributes['team'] ?? null,
                'privilege' => $attributes['privilege'] ?? 'PLAYER',
                'expires_at' => now()->addDays(7),
            ]);
            $this->revisions->bump($locked);

            return [$invite, $token];
        });
    }
}
