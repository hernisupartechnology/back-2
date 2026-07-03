<?php

namespace App\Observers;

use App\Models\Medication;
use App\Models\MedicationIntakeLog;

/**
 * Observer de registros de tomas.
 * Responsabilidades:
 * - Calcula delay_minutes al registrar una toma (taken_at - scheduled_datetime en minutos)
 * - Determina el status de la toma (tomado | atrasado) según el delay
 * - Recalcula remaining_doses en el medicamento padre
 */
class MedicationIntakeObserver
{
    public function creating(MedicationIntakeLog $log): void
    {
        $this->calcularDelay($log);
    }

    public function updating(MedicationIntakeLog $log): void
    {
        $this->calcularDelay($log);
    }

    public function created(MedicationIntakeLog $log): void
    {
        $this->recalcularRemainingDoses($log);
    }

    public function updated(MedicationIntakeLog $log): void
    {
        if ($log->wasChanged(['status', 'taken_at'])) {
            $this->recalcularRemainingDoses($log);
        }
    }

    /**
     * Calcula delay_minutes y ajusta status según las reglas de negocio:
     * - taken_at dentro de ±30 min de scheduled_datetime → "tomado"
     * - taken_at con más de 30 min de retraso → "atrasado"
     * - delay negativo: tomado antes de tiempo (se permite, status = "tomado")
     */
    private function calcularDelay(MedicationIntakeLog $log): void
    {
        if ($log->taken_at && $log->scheduled_datetime) {
            // diffInMinutes devuelve positivo si taken_at > scheduled
            $delay = $log->scheduled_datetime->diffInMinutes($log->taken_at, false);
            $log->delay_minutes = (int) $delay;

            // Determinar status automáticamente si se está registrando la toma ahora
            if ($log->status !== 'omitido' && $log->status !== 'pospuesto') {
                $log->status = ($delay > 30) ? 'atrasado' : 'tomado';
            }
        }
    }

    /**
     * Recalcula remaining_doses sumando todas las tomas registradas como "tomado" o "atrasado".
     */
    private function recalcularRemainingDoses(MedicationIntakeLog $log): void
    {
        $medication = Medication::find($log->medication_id);

        if (! $medication || is_null($medication->quantity)) {
            return;
        }

        $tomasRegistradas = MedicationIntakeLog::where('medication_id', $medication->id)
            ->whereIn('status', ['tomado', 'atrasado'])
            ->count();

        $medication->updateQuietly([
            'remaining_doses' => max(0, $medication->quantity - $tomasRegistradas),
        ]);
    }
}
