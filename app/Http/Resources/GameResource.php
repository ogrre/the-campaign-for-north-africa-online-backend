<?php

namespace App\Http\Resources;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Game */
class GameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $membership = $request->user() ? $this->memberships->firstWhere('user_id', $request->user()->id) : null;

        return [
            'id' => $this->id, 'name' => $this->name, 'status' => $this->status->value,
            'ruleset' => $this->ruleset, 'revision' => $this->revision,
            'creator_id' => $this->creator_id,
            'scenario' => ['id' => $this->scenario->id, 'slug' => $this->scenario->slug, 'name' => $this->scenario->name, 'source_reference' => $this->scenario->source_reference],
            'turn' => $this->turnState?->turn,
            'operation_stage' => $this->turnState?->operation_stage,
            'phase' => $this->turnState?->phase,
            'players' => MembershipResource::collection($this->whenLoaded('memberships')),
            'you' => $membership ? new MembershipResource($membership) : null,
            'started_at' => $this->started_at,
        ];
    }
}
