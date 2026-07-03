<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para editar los datos generales de una cita/necesidad.
 * No cambia el estado — eso lo maneja PATCH /appointments/{id}/status.
 */
class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'doctor_name_free' => 'nullable|string|max:255',
            'specialty' => 'sometimes|string|max:255',
            'appointment_type' => 'nullable|in:consulta,control,urgencias,domiciliaria,telemedicina',
            'ips' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',

            'need_reason' => 'nullable|string',
            'need_urgency' => 'nullable|in:rutina,prioritaria,urgente',
            'max_days_to_schedule' => 'nullable|integer|min:1|max:365',
            'alert_days_before_scheduling' => 'nullable|integer|min:1|max:365',

            'appointment_date' => 'nullable|date',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',

            'is_recurring' => 'nullable|boolean',
            'recurrence_type' => 'nullable|in:semanal,mensual,bimestral,trimestral,semestral,anual',
            'alert_days_before_appointment' => 'nullable|integer|min:1|max:30',
        ];
    }
}
