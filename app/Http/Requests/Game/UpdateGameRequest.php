<?php

namespace App\Http\Requests\Game;

class UpdateGameRequest extends RevisionRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [...parent::rules(), 'name' => ['required', 'string', 'max:120']];
    }
}
