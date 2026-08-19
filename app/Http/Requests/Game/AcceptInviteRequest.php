<?php

namespace App\Http\Requests\Game;

use App\Domain\Multiplayer\TeamCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcceptInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['team' => ['nullable', Rule::enum(TeamCode::class)]];
    }
}
