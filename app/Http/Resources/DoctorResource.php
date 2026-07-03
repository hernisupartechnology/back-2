<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de médico del catálogo del hogar.
 */
class DoctorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'name' => $this->name,
            'specialty' => $this->specialty,
            'registration_number' => $this->registration_number,
            'phone' => $this->phone,
            'email' => $this->email,
            'ips' => $this->ips,
            'address' => $this->address,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
        ];
    }
}
