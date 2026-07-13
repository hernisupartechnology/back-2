<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\Exam;
use App\Models\Medication;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\Vaccination;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Corre diariamente a las 7:00 AM (Scheduler). Revisa citas, medicamentos,
 * exámenes, remisiones y vacunas vencidas o próximas a vencer, genera
 * notificaciones in-app y actualiza automáticamente los estados que
 * expiran por tiempo (medicamentos vencidos, remisiones vencidas).
 *
 * Nota de escala: este job recorre TODA la plataforma (no un solo hogar) una
 * vez al día, así que su costo crece con el número total de usuarios, no por
 * hogar. Las consultas de cada check* están acotadas en SQL a lo que
 * realmente puede disparar una notificación (ver comentarios puntuales), y
 * se usa `each()` (chunking automático de Laravel) en vez de `get()` para no
 * cargar la tabla completa en memoria de una sola vez.
 */
class CheckHealthAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    private Carbon $today;

    /**
     * "user_id|type|model_id" de todo lo ya notificado hoy — precargado UNA
     * sola vez al inicio del job. Antes, notifyOnce() consultaba esto con una
     * query por cada cita/medicamento/remisión/examen/vacuna activa en TODA
     * la plataforma (N queries por corrida, una por fila revisada); ahora es
     * una sola query y el resto es un chequeo en memoria.
     */
    private Collection $notifiedToday;

    public function handle(): void
    {
        $this->today = Carbon::today('America/Bogota');

        $this->notifiedToday = Notification::whereBetween('created_at', [$this->today->copy()->utc(), $this->today->copy()->addDay()->utc()])
            ->get(['user_id', 'type', 'data'])
            ->map(fn (Notification $n) => $n->user_id.'|'.$n->type.'|'.($n->data['model_id'] ?? ''))
            ->flip();

        $this->checkAppointments();
        $this->checkMedications();
        $this->checkExams();
        $this->checkReferrals();
        $this->checkVaccinations();
    }

    private function checkAppointments(): void
    {
        // Todas las condiciones de abajo solo pueden dispararse para: una
        // "necesidad" sin agendar, una cita reprogramada (sin importar su
        // fecha), o una cita cuya fecha cae mañana/ayer. Cualquier otra cita
        // activa (programada meses en el futuro, por ejemplo) nunca hace
        // match con ningún notifyOnce() de abajo — acotar acá en SQL evita
        // traer años de historial de citas de toda la plataforma a memoria
        // solo para descartarlas una por una en PHP.
        $active = Appointment::whereNotIn('status', ['realizada', 'cancelada', 'no_asistio'])
            ->where(function ($q) {
                $q->where('is_need', true)
                    ->orWhere('status', 'reprogramada')
                    ->orWhereBetween('appointment_date', [$this->today->copy()->subDay(), $this->today->copy()->addDays(2)]);
            });

        $active->each(function (Appointment $appointment) {
            if ($appointment->is_need) {
                $daysElapsed = $appointment->need_registered_date
                    ? $appointment->need_registered_date->copy()->startOfDay()->diffInDays($this->today, false)
                    : 0;

                if ($appointment->need_urgency === 'urgente') {
                    $this->notifyOnce($appointment->user_id, 'appointment.need_urgent', Appointment::class, $appointment->id,
                        '🔴 Necesidad urgente sin agendar', "Aún no has agendado tu cita de {$appointment->specialty} — es urgente.", 'danger');
                } elseif ($daysElapsed >= $appointment->max_days_to_schedule) {
                    $this->notifyOnce($appointment->user_id, 'appointment.need_overdue', Appointment::class, $appointment->id,
                        '🔴 Plazo vencido para agendar', "Superaste el plazo para agendar tu cita de {$appointment->specialty}.", 'danger');
                } elseif ($daysElapsed >= $appointment->max_days_to_schedule - $appointment->alert_days_before_scheduling) {
                    $this->notifyOnce($appointment->user_id, 'appointment.need_soon', Appointment::class, $appointment->id,
                        '🟡 Agenda pronto tu cita', "Se acerca el plazo para agendar tu cita de {$appointment->specialty}.", 'warning');
                }

                return;
            }

            if (! $appointment->appointment_date) {
                return;
            }

            $appointmentDay = $appointment->appointment_date->copy()->startOfDay();

            if ($appointmentDay->isSameDay($this->today->copy()->addDay())) {
                $this->notifyOnce($appointment->user_id, 'appointment.tomorrow', Appointment::class, $appointment->id,
                    '📅 Cita mañana', "Recuerda tu cita de {$appointment->specialty} mañana.", 'info');
            } elseif ($appointmentDay->isSameDay($this->today->copy()->subDay()) && $appointment->status !== 'realizada') {
                $this->notifyOnce($appointment->user_id, 'appointment.confirm_attendance', Appointment::class, $appointment->id,
                    '🟡 ¿Asististe a tu cita?', "Tu cita de {$appointment->specialty} de ayer aún no fue actualizada.", 'warning');
            }

            if ($appointment->status === 'reprogramada' && $appointment->updated_at->diffInDays($this->today) > 10) {
                $this->notifyOnce($appointment->user_id, 'appointment.reprogramada_pending', Appointment::class, $appointment->id,
                    '🟡 Cita reprogramada sin nueva fecha', "Tu cita de {$appointment->specialty} sigue sin una nueva fecha desde hace más de 10 días.", 'warning');
            }
        });
    }

    private function checkMedications(): void
    {
        Medication::where('is_recurring', true)
            ->whereNotIn('status', ['completado', 'suspendido'])
            ->whereNotNull('end_date')
            ->each(function (Medication $medication) {
                $daysLeft = $medication->days_until_expiration;
                if ($daysLeft === null) {
                    return;
                }

                if ($daysLeft < 0) {
                    $this->notifyOnce($medication->user_id, 'medication.expired', Medication::class, $medication->id,
                        '🔴 Medicamento vencido', "{$medication->name} venció sin renovar.", 'danger');
                } elseif ($daysLeft <= 3) {
                    $this->notifyOnce($medication->user_id, 'medication.expiring_soon', Medication::class, $medication->id,
                        '🔴 Renovación urgente', "{$medication->name} vence en {$daysLeft} día(s).", 'danger');
                } elseif ($daysLeft <= $medication->alert_days_before) {
                    $this->notifyOnce($medication->user_id, 'medication.renewal_reminder', Medication::class, $medication->id,
                        '🟡 Renovación próxima', "{$medication->name} vence en {$daysLeft} día(s).", 'warning');
                }
            });

        // autorizado sin reclamar y con el tratamiento ya vencido → vencido automático
        Medication::where('status', 'autorizado')
            ->whereNotNull('end_date')
            ->where('end_date', '<', $this->today)
            ->get()
            ->each(function (Medication $medication) {
                $medication->update(['status' => 'vencido']);
                $this->notifyOnce($medication->user_id, 'medication.auto_expired', Medication::class, $medication->id,
                    '🔴 Autorización vencida sin reclamar', "{$medication->name} venció sin ser reclamado en farmacia.", 'danger');
            });
    }

    private function checkExams(): void
    {
        Exam::where('status', 'autorizado')
            ->whereNull('scheduled_date')
            ->where('updated_at', '<=', $this->today->copy()->subDays(15))
            ->each(fn (Exam $exam) => $this->notifyOnce($exam->user_id, 'exam.not_scheduled', Exam::class, $exam->id,
                '🟡 Examen sin agendar', "Tu examen {$exam->name} está autorizado hace más de 15 días sin agendar.", 'warning'));

        Exam::where('status', 'resultado_disponible')
            ->where('result_date', '<=', $this->today->copy()->subDays(7))
            ->each(fn (Exam $exam) => $this->notifyOnce($exam->user_id, 'exam.result_pending_delivery', Exam::class, $exam->id,
                '🔵 Resultado sin entregar al médico', "El resultado de {$exam->name} lleva más de 7 días disponible.", 'info'));
    }

    private function checkReferrals(): void
    {
        Referral::where('status', 'autorizada')->whereNotNull('authorization_expiry_date')
            ->each(function (Referral $referral) {
                $daysLeft = $referral->days_until_expiration;
                if ($daysLeft === null) {
                    return;
                }

                if ($daysLeft < 0) {
                    $referral->update(['status' => 'vencida']);
                    $this->notifyOnce($referral->user_id, 'referral.expired', Referral::class, $referral->id,
                        '🔴 Remisión vencida', "La remisión a {$referral->specialty} venció sin ser usada.", 'danger');
                } elseif ($daysLeft <= 5) {
                    $this->notifyOnce($referral->user_id, 'referral.expiring_urgent', Referral::class, $referral->id,
                        '🔴 Remisión por vencer', "La remisión a {$referral->specialty} vence en {$daysLeft} día(s).", 'danger');
                } elseif ($daysLeft <= 15) {
                    $this->notifyOnce($referral->user_id, 'referral.expiring_soon', Referral::class, $referral->id,
                        '🟡 Remisión sin agendar', "La remisión a {$referral->specialty} vence en {$daysLeft} día(s).", 'warning');
                }
            });
    }

    private function checkVaccinations(): void
    {
        Vaccination::whereNotNull('next_dose_date')
            ->whereBetween('next_dose_date', [$this->today, $this->today->copy()->addDays(30)])
            ->each(fn (Vaccination $v) => $this->notifyOnce($v->user_id, 'vaccination.next_dose_due', Vaccination::class, $v->id,
                '🟡 Próxima dosis de vacuna', "La próxima dosis de {$v->vaccine_name} está programada para el {$v->next_dose_date->format('d/m/Y')}.", 'warning'));
    }

    /** Crea la notificación solo si no se envió una igual hoy (evita duplicados si el job se re-ejecuta). */
    private function notifyOnce(int $userId, string $type, string $modelType, int $modelId, string $title, string $body, string $priority): void
    {
        $key = $userId.'|'.$type.'|'.$modelId;

        if ($this->notifiedToday->has($key)) {
            return;
        }

        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => ['model_type' => $modelType, 'model_id' => $modelId],
            'priority' => $priority,
        ]);

        // Marca en memoria también — evita crear duplicados si esta misma
        // corrida del job vuelve a llamar notifyOnce() con la misma clave.
        $this->notifiedToday->put($key, true);
    }
}
