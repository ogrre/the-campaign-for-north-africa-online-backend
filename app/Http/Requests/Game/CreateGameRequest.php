<?php

namespace App\Http\Requests\Game;

use App\Domain\Multiplayer\TeamCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'scenario_id' => ['required', 'uuid', 'exists:scenario_definitions,id'], 'team' => ['required', Rule::enum(TeamCode::class)]];
    }
}
