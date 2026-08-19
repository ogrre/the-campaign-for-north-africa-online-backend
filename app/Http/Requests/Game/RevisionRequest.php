<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class RevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'min:1']];
    }
}
