<?php

namespace App\Http\Resources;

use App\Services\TrafficLightService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'household_id' => $this->household_id,
            'user_id' => $this->user_id,
            'registered_by' => $this->registered_by,
            'specialty' => $this->specialty,
            'referring_doctor_id' => $this->referring_doctor_id,
            'referred_doctor_id' => $this->referred_doctor_id,
            'reason' => $this->reason,
            'urgency' => $this->urgency,
            'status' => $this->status,
            'denied_reason' => $this->denied_reason,
            'authorization_date' => $this->authorization_date?->format('Y-m-d'),
            'authorization_number' => $this->authorization_number,
            'authorization_expiry_date' => $this->authorization_expiry_date?->format('Y-m-d'),
            'scheduled_appointment_id' => $this->scheduled_appointment_id,
            'days_until_expiration' => $this->days_until_expiration,
            'traffic_light' => app(TrafficLightService::class)->forReferral($this->resource),
            'notes' => $this->notes,
            'referring_doctor' => $this->whenLoaded('referringDoctor', fn () => new DoctorResource($this->referringDoctor)),
            'referred_doctor' => $this->whenLoaded('referredDoctor', fn () => new DoctorResource($this->referredDoctor)),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id, 'name' => $this->patient->name, 'avatar' => $this->patient->avatar,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
