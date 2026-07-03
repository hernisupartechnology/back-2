<?php

namespace App\Http\Resources;

use App\Services\TrafficLightService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de medicamento — incluye el semáforo de renovación calculado en servidor.
 */
class MedicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $renewalTrafficLight = app(TrafficLightService::class)->forMedicationRenewal($this->resource);

        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'household_id' => $this->household_id,
            'user_id' => $this->user_id,
            'registered_by' => $this->registered_by,
            'name' => $this->name,
            'active_ingredient' => $this->active_ingredient,
            'presentation' => $this->presentation,
            'dosage' => $this->dosage,
            'frequency' => $this->frequency,
            'duration_days' => $this->duration_days,
            'quantity' => $this->quantity,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_recurring' => $this->is_recurring,
            'recurrence_days' => $this->recurrence_days,
            'alert_days_before' => $this->alert_days_before,
            'status' => $this->status,
            'denied_reason' => $this->denied_reason,
            'authorization_date' => $this->authorization_date?->format('Y-m-d'),
            'authorization_number' => $this->authorization_number,
            'claimed_date' => $this->claimed_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'track_intake' => $this->track_intake,
            'intake_quantity_per_dose' => $this->intake_quantity_per_dose,
            'remaining_doses' => $this->remaining_doses,
            'low_stock_alert_doses' => $this->low_stock_alert_doses,
            'days_until_expiration' => $this->days_until_expiration,
            'renewal_traffic_light' => $renewalTrafficLight,

            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'name' => $this->patient->name,
                'avatar' => $this->patient->avatar,
            ]),
            'schedules' => $this->whenLoaded('schedules'),
            'renewals' => $this->whenLoaded('renewals'),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
