<?php

namespace App\Models;

use App\Domain\Multiplayer\EventVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property int $sequence
 * @property string $type
 * @property int|null $actor_user_id
 * @property string|null $team_id
 * @property EventVisibility $visibility
 * @property array<string, mixed>|null $payload
 */
class GameEvent extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['visibility' => EventVisibility::class, 'payload' => 'array'];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
