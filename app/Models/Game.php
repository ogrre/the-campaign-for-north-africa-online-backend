<?php

namespace App\Models;

use App\Domain\Multiplayer\GameStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property int $creator_id
 * @property string $scenario_definition_id
 * @property GameStatus $status
 * @property string $ruleset
 * @property int $revision
 * @property int $event_sequence
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property-read User $creator
 * @property-read ScenarioDefinition $scenario
 * @property-read Collection<int, GameTeam> $teams
 * @property-read Collection<int, GameMembership> $memberships
 * @property-read GameTurnState|null $turnState
 * @property-read Collection<int, PendingDecision> $decisions
 */
class Game extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => GameStatus::class, 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** @return BelongsTo<ScenarioDefinition, $this> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(ScenarioDefinition::class, 'scenario_definition_id');
    }

    /** @return HasMany<GameTeam, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(GameTeam::class);
    }

    /** @return HasMany<GameMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(GameMembership::class);
    }

    /** @return HasOne<GameTurnState, $this> */
    public function turnState(): HasOne
    {
        return $this->hasOne(GameTurnState::class);
    }

    /** @return HasMany<PendingDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(PendingDecision::class);
    }

    /** @return HasMany<GameEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }
}
