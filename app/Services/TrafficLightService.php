<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Medication;
use App\Models\Referral;
use Carbon\Carbon;

/**
 * Calcula el nivel del semáforo (rojo/amarillo/verde/gris) para citas,
 * medicamentos recurrentes y remisiones. Espejo en PHP de
 * front/src/utils/statusHelpers.ts — misma lógica, dos lenguajes.
 */
class TrafficLightService
{
    private const FINAL_APPOINTMENT_STATUSES = ['realizada', 'cancelada', 'no_asistio'];

    /** @return array{level: string, label: string} */
    public function forAppointment(Appointment $appointment): array
    {
        if (in_array($appointment->status, self::FINAL_APPOINTMENT_STATUSES, true)) {
            return ['level' => 'grey', 'label' => 'Sin acción'];
        }

        if ($appointment->is_need) {
            return $this->forNeed($appointment);
        }

        if (! $appointment->appointment_date) {
            return ['level' => 'grey', 'label' => 'Sin fecha'];
        }

        if ($appointment->status === 'reprogramada') {
            return ['level' => 'yellow', 'label' => 'Reprogramada — sin nueva fecha'];
        }

        $today = Carbon::today('America/Bogota');
        $diffDays = $today->diffInDays($appointment->appointment_date->copy()->startOfDay(), false);

        if ($diffDays < 0) {
            return ['level' => 'red', 'label' => 'Fecha vencida'];
        }
        if ($diffDays <= 1) {
            return ['level' => 'red', 'label' => $diffDays === 0 ? '¡Hoy!' : '¡Mañana!'];
        }
        if ($diffDays <= 5) {
            return ['level' => 'yellow', 'label' => "En {$diffDays} días"];
        }

        return ['level' => 'green', 'label' => "En {$diffDays} días"];
    }

    /** @return array{level: string, label: string} */
    private function forNeed(Appointment $appointment): array
    {
        $urgency = $appointment->need_urgency;
        $registeredDate = $appointment->need_registered_date ?? Carbon::today('America/Bogota');
        $daysElapsed = $registeredDate->copy()->startOfDay()->diffInDays(Carbon::today('America/Bogota'), false);

        if ($urgency === 'urgente') {
            return ['level' => 'red', 'label' => 'Urgente — agendar hoy'];
        }

        if ($daysElapsed >= $appointment->max_days_to_schedule) {
            return ['level' => 'red', 'label' => 'Plazo vencido'];
        }

        $alertThreshold = $appointment->max_days_to_schedule - $appointment->alert_days_before_scheduling;
        if ($daysElapsed >= $alertThreshold || $urgency === 'prioritaria') {
            return ['level' => 'yellow', 'label' => 'Agendar pronto'];
        }

        return ['level' => 'green', 'label' => 'Pendiente de agendar'];
    }

    /** @return array{level: string, label: string}|null */
    public function forMedicationRenewal(Medication $medication): ?array
    {
        if (! $medication->is_recurring || ! $medication->end_date) {
            return null;
        }

        $daysLeft = $medication->days_until_expiration;

        if ($daysLeft === null) {
            return null;
        }
        if ($daysLeft < 0) {
            return ['level' => 'red', 'label' => 'Medicamento vencido'];
        }
        if ($daysLeft <= 3) {
            return ['level' => 'red', 'label' => "Vence en {$daysLeft} días"];
        }
        if ($daysLeft <= $medication->alert_days_before) {
            return ['level' => 'yellow', 'label' => "Vence en {$daysLeft} días"];
        }

        return ['level' => 'green', 'label' => "{$daysLeft} días restantes"];
    }

    /** @return array{level: string, label: string}|null */
    public function forReferral(Referral $referral): ?array
    {
        if ($referral->status !== 'autorizada' || ! $referral->authorization_expiry_date) {
            return null;
        }

        $daysLeft = $referral->days_until_expiration;

        if ($daysLeft === null) {
            return null;
        }
        if ($daysLeft < 0) {
            return ['level' => 'grey', 'label' => 'Autorización vencida'];
        }
        if ($daysLeft <= 5) {
            return ['level' => 'red', 'label' => "Vence en {$daysLeft} días"];
        }
        if ($daysLeft <= 15) {
            return ['level' => 'yellow', 'label' => "Vence en {$daysLeft} días"];
        }

        return ['level' => 'green', 'label' => "{$daysLeft} días restantes"];
    }
}
