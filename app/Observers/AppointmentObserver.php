<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\AppointmentRecurrenceService;

/**
 * Observer de citas médicas.
 * Responsabilidades:
 * - Calcula recurrence_interval_days desde recurrence_type al guardar
 * - Genera la siguiente cita en la cadena cuando una cita recurrente es marcada como "realizada"
 *   (delegado en AppointmentRecurrenceService, compartido con la generación manual del controller)
 */
class AppointmentObserver
{
    public function __construct(private readonly AppointmentRecurrenceService $recurrenceService) {}

    public function creating(Appointment $appointment): void
    {
        $this->calcularRecurrenceIntervalDays($appointment);
    }

    public function updating(Appointment $appointment): void
    {
        $this->calcularRecurrenceIntervalDays($appointment);
    }

    public function updated(Appointment $appointment): void
    {
        if ($appointment->wasChanged('status') && $this->recurrenceService->shouldGenerateNext($appointment)) {
            $this->recurrenceService->generateNext($appointment);
        }
    }

    /**
     * Calcula automáticamente recurrence_interval_days desde recurrence_type.
     */
    private function calcularRecurrenceIntervalDays(Appointment $appointment): void
    {
        if ($appointment->is_recurring && $appointment->recurrence_type) {
            $appointment->recurrence_interval_days =
                AppointmentRecurrenceService::RECURRENCE_DAYS[$appointment->recurrence_type] ?? null;
        }
    }
}
