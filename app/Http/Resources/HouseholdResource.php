<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de hogar — transforma el modelo Household a respuesta JSON estándar.
 */
class HouseholdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'owner_id' => $this->owner_id,
            'avatar' => $this->avatar,
            'owner' => $this->whenLoaded('owner', fn () => new UserResource($this->owner)),
            'members' => UserResource::collection($this->whenLoaded('members')),
        ];
    }
}
