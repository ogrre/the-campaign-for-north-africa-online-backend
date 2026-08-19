<?php

namespace App\Http\Resources;

use App\Models\GameMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GameMembership */
class MembershipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => ['id' => $this->user->id, 'name' => $this->user->name],
            'team' => $this->team?->code?->value,
            'privilege' => $this->privilege->value,
            'roles' => $this->roles->pluck('code')->values(),
        ];
    }
}
