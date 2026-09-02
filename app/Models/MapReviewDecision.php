<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapReviewDecision extends Model
{
    protected $fillable = ['session_id', 'reviewer_id', 'map_section', 'region_id', 'entity_type', 'entity_id', 'decision', 'value', 'notes', 'source', 'action_group_id'];

    protected function casts(): array
    {
        return ['value' => 'array', 'source' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<MapReviewSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(MapReviewSession::class, 'session_id');
    }
}
