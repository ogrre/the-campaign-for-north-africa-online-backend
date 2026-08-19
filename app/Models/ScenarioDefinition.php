<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property int|null $initial_turn
 * @property int|null $initial_operation_stage
 * @property string $source_document
 * @property string $source_reference
 * @property bool $setup_complete
 */
class ScenarioDefinition extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['setup_complete' => 'boolean'];
    }
}
