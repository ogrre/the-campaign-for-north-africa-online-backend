<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $game_id @property int $turn @property int $operation_stage @property string $phase */
class GameTurnState extends Model
{
    protected $primaryKey = 'game_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
