<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de usuario — transforma el modelo User a respuesta JSON estándar.
 * Nunca expone password ni remember_token.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'role' => $this->role,
            'household_id' => $this->household_id,
            'phone' => $this->phone,
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'gender' => $this->gender,
            'blood_type' => $this->blood_type,
            'eps' => $this->eps,
            'ips_preferida' => $this->ips_preferida,
            'numero_afiliado' => $this->numero_afiliado,
            'is_minor' => $this->is_minor,
            'supervised_by' => $this->supervised_by,
            'track_vital_signs' => $this->track_vital_signs,
            'dark_mode' => $this->dark_mode,

            // Relaciones (solo si fueron cargadas con eager loading)
            'household' => $this->whenLoaded('household', fn () => [
                'id' => $this->household->id,
                'name' => $this->household->name,
            ]),
            'allergies' => $this->whenLoaded('allergies'),
            'chronic_conditions' => $this->whenLoaded('chronicConditions'),
            'vital_sign_range' => $this->whenLoaded('vitalSignRange'),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
