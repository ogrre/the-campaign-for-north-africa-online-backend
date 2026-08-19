<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property string $code @property string $name @property bool $required_to_start */
class GameRoleDefinition extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['required_to_start' => 'boolean'];
    }
}
