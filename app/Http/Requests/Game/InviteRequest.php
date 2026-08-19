<?php

namespace App\Http\Requests\Game;

use App\Domain\Multiplayer\MembershipPrivilege;
use App\Domain\Multiplayer\TeamCode;
use Illuminate\Validation\Rule;

class InviteRequest extends RevisionRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [...parent::rules(), 'email' => ['nullable', 'email'], 'team' => ['nullable', Rule::enum(TeamCode::class)], 'privilege' => ['nullable', Rule::enum(MembershipPrivilege::class)]];
    }
}
