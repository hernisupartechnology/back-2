<?php

namespace App\Http\Resources;

use App\Services\TrafficLightService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de cita médica / necesidad — incluye el semáforo calculado en servidor.
 */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $trafficLight = app(TrafficLightService::class)->forAppointment($this->resource);

        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'user_id' => $this->user_id,
            'registered_by' => $this->registered_by,
            'doctor_id' => $this->doctor_id,
            'doctor_name_free' => $this->doctor_name_free,
            'specialty' => $this->specialty,
            'appointment_type' => $this->appointment_type,
            'ips' => $this->ips,
            'address' => $this->address,

            'is_need' => $this->is_need,
            'need_reason' => $this->need_reason,
            'need_urgency' => $this->need_urgency,
            'need_registered_date' => $this->need_registered_date?->format('Y-m-d'),
            'max_days_to_schedule' => $this->max_days_to_schedule,
            'alert_days_before_scheduling' => $this->alert_days_before_scheduling,

            'appointment_date' => $this->appointment_date?->toISOString(),
            'reason' => $this->reason,
            'diagnosis' => $this->diagnosis,
            'notes' => $this->notes,
            'status' => $this->status,
            'cancelled_reason' => $this->cancelled_reason,
            'cancelled_by' => $this->cancelled_by,

            'next_appointment_date' => $this->next_appointment_date?->format('Y-m-d'),
            'next_appointment_notes' => $this->next_appointment_notes,
            'next_appointment_specialty' => $this->next_appointment_specialty,

            'is_recurring' => $this->is_recurring,
            'recurrence_type' => $this->recurrence_type,
            'recurrence_interval_days' => $this->recurrence_interval_days,
            'alert_days_before_appointment' => $this->alert_days_before_appointment,
            'parent_appointment_id' => $this->parent_appointment_id,
            'recurrence_number' => $this->recurrence_number,
            'next_recurrence_generated' => $this->next_recurrence_generated,

            'traffic_light' => $trafficLight,

            'doctor' => $this->whenLoaded('doctor', fn () => new DoctorResource($this->doctor)),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'name' => $this->patient->name,
                'avatar' => $this->patient->avatar,
            ]),
            'medications' => $this->whenLoaded('medications'),
            'exams' => $this->whenLoaded('exams'),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
