<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapReviewSession extends Model
{
    use HasUuids;

    protected $fillable = ['reviewer_id', 'map_section', 'region_id', 'status', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return HasMany<MapReviewDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(MapReviewDecision::class, 'session_id');
    }
}
