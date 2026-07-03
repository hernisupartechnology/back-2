<?php

namespace App\Http\Requests\Medication;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para editar los datos generales de un medicamento.
 * No cambia el estado — eso lo maneja PATCH /medications/{id}/status.
 */
class UpdateMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'active_ingredient' => 'nullable|string|max:255',
            'presentation' => 'nullable|string|max:100',
            'dosage' => 'sometimes|string|max:100',
            'frequency' => 'sometimes|string|max:255',
            'duration_days' => 'nullable|integer|min:1|max:3650',
            'quantity' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',

            'is_recurring' => 'nullable|boolean',
            'recurrence_days' => 'nullable|integer|min:1|max:365',
            'alert_days_before' => 'nullable|integer|min:3|max:30',

            'authorization_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',

            'track_intake' => 'nullable|boolean',
            'intake_quantity_per_dose' => 'nullable|numeric|min:0.1',
            'low_stock_alert_doses' => 'nullable|integer|min:1|max:100',
        ];
    }
}
