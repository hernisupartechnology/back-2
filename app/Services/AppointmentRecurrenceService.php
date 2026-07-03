<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentRecurrenceLog;
use App\Models\Notification;

/**
 * Genera la siguiente cita en una cadena recurrente.
 * Usado automáticamente por AppointmentObserver (al marcar "realizada")
 * y manualmente por AppointmentController::generateNext (recuperación de casos no cubiertos).
 */
class AppointmentRecurrenceService
{
    public const RECURRENCE_DAYS = [
        'semanal' => 7,
        'mensual' => 30,
        'bimestral' => 60,
        'trimestral' => 90,
        'semestral' => 180,
        'anual' => 365,
    ];

    public function shouldGenerateNext(Appointment $appointment): bool
    {
        return $appointment->status === 'realizada'
            && $appointment->is_recurring
            && ! $appointment->next_recurrence_generated;
    }

    public function generateNext(Appointment $appointment): Appointment
    {
        $siguienteNumero = $appointment->recurrence_number + 1;

        $fechaSiguiente = null;
        $estadoSiguiente = 'necesidad';

        if ($appointment->appointment_date && $appointment->recurrence_interval_days) {
            $fechaSiguiente = $appointment->appointment_date
                ->copy()
                ->addDays($appointment->recurrence_interval_days);
            $estadoSiguiente = 'programada';
        }

        $parentId = $appointment->parent_appointment_id ?? $appointment->id;

        $siguienteCita = Appointment::create([
            'household_id' => $appointment->household_id,
            'user_id' => $appointment->user_id,
            'registered_by' => $appointment->user_id,
            'doctor_id' => $appointment->doctor_id,
            'doctor_name_free' => $appointment->doctor_name_free,
            'specialty' => $appointment->specialty,
            'appointment_type' => $appointment->appointment_type,
            'ips' => $appointment->ips,
            'is_need' => $estadoSiguiente === 'necesidad',
            'need_reason' => $appointment->reason,
            'need_urgency' => 'rutina',
            'need_registered_date' => now()->toDateString(),
            'max_days_to_schedule' => $appointment->max_days_to_schedule,
            'alert_days_before_scheduling' => $appointment->alert_days_before_scheduling,
            'appointment_date' => $fechaSiguiente,
            'status' => $estadoSiguiente,
            'is_recurring' => true,
            'recurrence_type' => $appointment->recurrence_type,
            'recurrence_interval_days' => $appointment->recurrence_interval_days,
            'alert_days_before_appointment' => $appointment->alert_days_before_appointment,
            'parent_appointment_id' => $parentId,
            'recurrence_number' => $siguienteNumero,
            'next_recurrence_generated' => false,
        ]);

        $appointment->updateQuietly(['next_recurrence_generated' => true]);

        AppointmentRecurrenceLog::create([
            'parent_appointment_id' => $parentId,
            'appointment_id' => $siguienteCita->id,
            'recurrence_number' => $siguienteNumero,
            'scheduled_date' => $fechaSiguiente,
            'status' => 'generada',
        ]);

        Notification::create([
            'user_id' => $appointment->user_id,
            'type' => 'appointment.recurrence_generated',
            'title' => '📅 Nueva cita de control generada',
            'body' => "Se generó tu próxima cita de {$appointment->specialty} — ".
                ($estadoSiguiente === 'programada'
                    ? "programada para el {$fechaSiguiente->format('d/m/Y')}"
                    : 'pendiente de agendar'),
            'data' => ['appointment_id' => $siguienteCita->id],
            'priority' => 'info',
        ]);

        return $siguienteCita;
    }
}
