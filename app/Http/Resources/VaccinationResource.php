<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VaccinationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $daysUntilNextDose = $this->next_dose_date
            ? (int) now()->startOfDay()->diffInDays($this->next_dose_date, false)
            : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'registered_by' => $this->registered_by,
            'vaccine_name' => $this->vaccine_name,
            'dose_number' => $this->dose_number,
            'application_date' => $this->application_date?->format('Y-m-d'),
            'applied_by' => $this->applied_by,
            'lot_number' => $this->lot_number,
            'ips_or_center' => $this->ips_or_center,
            'next_dose_date' => $this->next_dose_date?->format('Y-m-d'),
            'days_until_next_dose' => $daysUntilNextDose,
            'notes' => $this->notes,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id, 'name' => $this->patient->name, 'avatar' => $this->patient->avatar,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
