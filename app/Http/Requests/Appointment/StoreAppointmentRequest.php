<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para registrar una necesidad o una cita ya agendada.
 * (Paso 1 del modal "Nueva cita / Necesidad" — Opción A vs Opción B).
 */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización de "quién puede crear para quién" se hace en el controller
    }

    public function rules(): array
    {
        $isNeed = $this->boolean('is_need');

        return [
            'user_id' => 'required|integer|exists:users,id',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'doctor_name_free' => 'nullable|string|max:255',
            'specialty' => 'required|string|max:255',
            'appointment_type' => 'nullable|in:consulta,control,urgencias,domiciliaria,telemedicina',
            'ips' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',

            'is_need' => 'required|boolean',

            // Necesidad (Opción A)
            'need_reason' => [Rule::requiredIf($isNeed), 'nullable', 'string'],
            'need_urgency' => 'nullable|in:rutina,prioritaria,urgente',
            'max_days_to_schedule' => 'nullable|integer|min:1|max:365',
            'alert_days_before_scheduling' => 'nullable|integer|min:1|max:365',

            // Cita agendada (Opción B)
            'appointment_date' => [Rule::requiredIf(! $isNeed), 'nullable', 'date'],
            'reason' => 'nullable|string',

            // Recurrencia (disponible en ambas opciones)
            'is_recurring' => 'nullable|boolean',
            'recurrence_type' => [Rule::requiredIf($this->boolean('is_recurring')), 'nullable', 'in:semanal,mensual,bimestral,trimestral,semestral,anual'],
            'alert_days_before_appointment' => 'nullable|integer|min:1|max:30',

            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'need_reason.required' => 'Cuéntanos el motivo de la necesidad.',
            'appointment_date.required' => 'Indica la fecha y hora de la cita.',
            'recurrence_type.required' => 'Selecciona la frecuencia de la recurrencia.',
        ];
    }
}
