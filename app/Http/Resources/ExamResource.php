<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'household_id' => $this->household_id,
            'user_id' => $this->user_id,
            'registered_by' => $this->registered_by,
            'name' => $this->name,
            'exam_type' => $this->exam_type,
            'lab_or_center' => $this->lab_or_center,
            'urgency' => $this->urgency,
            'status' => $this->status,
            'denied_reason' => $this->denied_reason,
            'authorization_date' => $this->authorization_date?->format('Y-m-d'),
            'authorization_number' => $this->authorization_number,
            'scheduled_date' => $this->scheduled_date?->toISOString(),
            'performed_date' => $this->performed_date?->format('Y-m-d'),
            'result_date' => $this->result_date?->format('Y-m-d'),
            'result_summary' => $this->result_summary,
            'delivered_to_doctor_date' => $this->delivered_to_doctor_date?->format('Y-m-d'),
            'cancelled_reason' => $this->cancelled_reason,
            'notes' => $this->notes,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id, 'name' => $this->patient->name, 'avatar' => $this->patient->avatar,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
