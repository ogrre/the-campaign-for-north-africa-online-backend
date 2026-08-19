<?php

namespace App\Models;

use App\Domain\Multiplayer\MembershipPrivilege;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $game_id
 * @property int $user_id
 * @property string|null $team_id
 * @property MembershipPrivilege $privilege
 * @property string $status
 * @property-read Game $game
 * @property-read User $user
 * @property-read GameTeam|null $team
 * @property-read Collection<int, GameRoleDefinition> $roles
 */
class GameMembership extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['privilege' => MembershipPrivilege::class];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<GameTeam, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(GameTeam::class, 'team_id');
    }

    /** @return BelongsToMany<GameRoleDefinition, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(GameRoleDefinition::class, 'game_player_roles', 'game_membership_id', 'role_code')->withTimestamps();
    }
}
