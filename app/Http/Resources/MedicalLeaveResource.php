<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalLeaveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'user_id' => $this->user_id,
            'registered_by' => $this->registered_by,
            'appointment_id' => $this->appointment_id,
            'issuing_doctor_id' => $this->issuing_doctor_id,
            'issuing_doctor_name_free' => $this->issuing_doctor_name_free,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'total_days' => $this->total_days,
            'diagnosis' => $this->diagnosis,
            'diagnosis_code' => $this->diagnosis_code,
            'leave_type' => $this->leave_type,
            'ips_issued' => $this->ips_issued,
            'notes' => $this->notes,
            'issuing_doctor' => $this->whenLoaded('issuingDoctor', fn () => new DoctorResource($this->issuingDoctor)),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id, 'name' => $this->patient->name, 'avatar' => $this->patient->avatar,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
