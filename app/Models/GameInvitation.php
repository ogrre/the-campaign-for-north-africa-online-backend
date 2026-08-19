<?php

namespace App\Models;

use App\Domain\Multiplayer\MembershipPrivilege;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $game_id
 * @property int $invited_by
 * @property string $token_hash
 * @property string|null $invited_email
 * @property string|null $team_code
 * @property MembershipPrivilege $privilege
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 */
class GameInvitation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['privilege' => MembershipPrivilege::class, 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'declined_at' => 'datetime'];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
