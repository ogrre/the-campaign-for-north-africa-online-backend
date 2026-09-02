<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MapReviewDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'uuid'],
            'map_section' => ['required', 'string', 'in:Map A,Map D,Map E'],
            'region_id' => ['nullable', 'string', 'max:80'],
            'entity_type' => ['required', 'string', 'in:hex,hexside,network,location'],
            'entity_id' => ['required', 'string', 'max:120'],
            'decision' => ['required', 'string', 'in:CONFIRMED,AMBIGUOUS,UNRESOLVED,SKIPPED'],
            'value' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', 'array'],
            'action_group_id' => ['nullable', 'uuid'],
        ];
    }
}
