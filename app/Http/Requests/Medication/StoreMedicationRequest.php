<?php

namespace App\Http\Requests\Medication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para registrar un nuevo medicamento — incluye la configuración
 * opcional de horarios de toma cuando se activa track_intake.
 */
class StoreMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorización de "para quién" se hace en el controller
    }

    public function rules(): array
    {
        $isRecurring = $this->boolean('is_recurring');
        $trackIntake = $this->boolean('track_intake');

        return [
            'user_id' => 'required|integer|exists:users,id',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'name' => 'required|string|max:255',
            'active_ingredient' => 'nullable|string|max:255',
            'presentation' => 'nullable|string|max:100',
            'dosage' => 'required|string|max:100',
            'frequency' => 'required|string|max:255',
            'duration_days' => 'nullable|integer|min:1|max:3650',
            'quantity' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',

            'is_recurring' => 'nullable|boolean',
            'recurrence_days' => [Rule::requiredIf($isRecurring), 'nullable', 'integer', 'min:1', 'max:365'],
            'alert_days_before' => 'nullable|integer|min:3|max:30',

            'status' => 'nullable|in:sin_orden,con_orden,en_autorizacion,autorizado,negado,reclamado,en_uso,completado,vencido,suspendido',
            'authorization_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',

            'track_intake' => 'nullable|boolean',
            'intake_quantity_per_dose' => [Rule::requiredIf($trackIntake), 'nullable', 'numeric', 'min:0.1'],
            'low_stock_alert_doses' => 'nullable|integer|min:1|max:100',

            'schedules' => 'nullable|array',
            'schedules.*.time_of_day' => 'required_with:schedules|date_format:H:i',
            'schedules.*.label' => 'nullable|string|max:100',
            'schedules.*.days_of_week' => 'nullable|array',
            'schedules.*.days_of_week.*' => 'integer|min:1|max:7', // ISO 8601: 1=lunes ... 7=domingo
            'schedules.*.reminder_minutes_before' => 'nullable|integer|in:0,5,10,15,30',
        ];
    }

    public function messages(): array
    {
        return [
            'recurrence_days.required' => 'Indica cada cuántos días se renueva el medicamento.',
            'intake_quantity_per_dose.required' => 'Indica la cantidad por toma para activar los recordatorios.',
        ];
    }
}
