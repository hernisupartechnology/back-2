<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de signo vital — incluye qué mediciones están fuera de rango
 * normal (spec: badge de alerta rojo si sistólica/glucosa/O2 se salen del rango).
 * El controller debe eager-cargar la relación `range` para evitar N+1.
 */
class VitalSignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $range = $this->range;
        $outOfRange = [];

        if ($range) {
            if ($this->systolic_pressure !== null && $range->isSystolicOutOfRange($this->systolic_pressure)) {
                $outOfRange[] = 'systolic_pressure';
            }
            if ($this->diastolic_pressure !== null && $range->isDiastolicOutOfRange($this->diastolic_pressure)) {
                $outOfRange[] = 'diastolic_pressure';
            }
            if ($this->blood_glucose !== null && $range->isGlucoseOutOfRange((float) $this->blood_glucose)) {
                $outOfRange[] = 'blood_glucose';
            }
            if ($this->oxygen_saturation !== null && $range->isOxygenOutOfRange($this->oxygen_saturation)) {
                $outOfRange[] = 'oxygen_saturation';
            }
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'registered_by' => $this->registered_by,
            'appointment_id' => $this->appointment_id,
            'measurement_date' => $this->measurement_date?->toISOString(),
            'systolic_pressure' => $this->systolic_pressure,
            'diastolic_pressure' => $this->diastolic_pressure,
            'heart_rate' => $this->heart_rate,
            'blood_glucose' => $this->blood_glucose,
            'weight' => $this->weight,
            'height' => $this->height,
            'temperature' => $this->temperature,
            'oxygen_saturation' => $this->oxygen_saturation,
            'notes' => $this->notes,
            'out_of_range' => $outOfRange,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id, 'name' => $this->patient->name, 'avatar' => $this->patient->avatar,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
