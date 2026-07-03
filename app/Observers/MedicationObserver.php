<?php

namespace App\Observers;

use App\Models\Medication;

/**
 * Observer de medicamentos.
 * Responsabilidades:
 * - Calcula end_date automáticamente al crear/actualizar (start_date + duration_days)
 * - Recalcula remaining_doses cuando cambia quantity
 */
class MedicationObserver
{
    public function creating(Medication $medication): void
    {
        $this->calcularEndDate($medication);
    }

    public function updating(Medication $medication): void
    {
        $this->calcularEndDate($medication);
    }

    /**
     * Calcula la fecha de fin del tratamiento.
     * end_date = start_date + duration_days - 1 (el primer día ya cuenta)
     */
    private function calcularEndDate(Medication $medication): void
    {
        if ($medication->start_date && $medication->duration_days > 0) {
            $medication->end_date = $medication->start_date
                ->copy()
                ->addDays($medication->duration_days - 1);
        }

        // Inicializar remaining_doses si se proporcionó quantity y no hay logs aún
        if ($medication->isDirty('quantity') && $medication->quantity !== null) {
            $medication->remaining_doses = $medication->quantity;
        }
    }
}
