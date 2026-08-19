<?php

namespace App\Models;

use App\Domain\Multiplayer\EventVisibility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $game_id
 * @property string|null $team_id
 * @property string $type
 * @property string $status
 * @property string|null $required_role_code
 * @property EventVisibility $visibility
 * @property array<string, mixed>|null $public_context
 * @property-read GameTeam|null $team
 * @property-read Collection<int, GameMembership> $participants
 */
class PendingDecision extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['visibility' => EventVisibility::class, 'public_context' => 'array', 'deadline_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<GameTeam, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(GameTeam::class);
    }

    /** @return BelongsToMany<GameMembership, $this> */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(GameMembership::class, 'decision_participants')->withPivot(['status', 'responded_at']);
    }
}
