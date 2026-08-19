<?php

namespace App\Http\Requests\Game;

use App\Domain\Multiplayer\TeamCode;
use Illuminate\Validation\Rule;

class JoinGameRequest extends RevisionRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [...parent::rules(), 'team' => ['required', Rule::enum(TeamCode::class)]];
    }
}
