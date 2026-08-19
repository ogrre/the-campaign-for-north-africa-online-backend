<?php

namespace App\Http\Requests\Game;

class AssignRoleRequest extends RevisionRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [...parent::rules(), 'role' => ['required', 'exists:game_role_definitions,code']];
    }
}
