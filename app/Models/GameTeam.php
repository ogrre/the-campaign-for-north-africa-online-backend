<?php

namespace App\Models;

use App\Domain\Multiplayer\TeamCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $game_id
 * @property TeamCode $code
 * @property string $name
 * @property-read Collection<int, GameMembership> $memberships
 */
class GameTeam extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['code' => TeamCode::class];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<GameMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(GameMembership::class, 'team_id');
    }
}
