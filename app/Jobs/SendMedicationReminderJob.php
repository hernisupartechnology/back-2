<?php

namespace App\Jobs;

use App\Models\MedicationIntakeLog;
use App\Models\MedicationSchedule;
use App\Models\Notification;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Corre cada minuto (Scheduler). Revisa los horarios de toma activos y envía
 * recordatorios en 3 momentos: antes de la hora, en la hora exacta, y si
 * pasaron más de 30 minutos sin registrar la toma. Idempotente: usa las
 * notificaciones ya creadas hoy para no reenviar el mismo aviso dos veces.
 */
class SendMedicationReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function handle(PushNotificationService $push): void
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $isoWeekday = $today->dayOfWeekIso;

        $schedules = MedicationSchedule::where('is_active', true)
            ->whereHas('medication', fn ($q) => $q->where('status', 'en_uso')->where('track_intake', true))
            ->with(['medication', 'user'])
            ->get()
            ->filter(fn (MedicationSchedule $s) => $s->appliesOnDay($isoWeekday) && $s->medication && $s->user);

        if ($schedules->isEmpty()) {
            return;
        }

        $userIds = $schedules->pluck('user_id')->unique()->all();

        $existingLogs = MedicationIntakeLog::whereIn('user_id', $userIds)
            ->whereDate('scheduled_datetime', $today->toDateString())
            ->get()
            ->keyBy(fn (MedicationIntakeLog $log) => $log->medication_id.'|'.$log->scheduled_datetime->format('Y-m-d H:i:s'));

        $todayNotifications = Notification::whereIn('user_id', $userIds)
            ->whereDate('created_at', $today->toDateString())
            ->get();

        foreach ($schedules as $schedule) {
            $scheduledAt = $today->copy()->setTimeFromTimeString((string) $schedule->time_of_day);
            $logKey = $schedule->medication_id.'|'.$scheduledAt->format('Y-m-d H:i:s');

            // Ya se registró esta toma (tomada, omitida o pospuesta) — nada que recordar.
            if ($existingLogs->has($logKey)) {
                continue;
            }

            $minutesUntil = (int) $now->diffInMinutes($scheduledAt, false);
            $reminderBefore = $schedule->reminder_minutes_before;

            if ($reminderBefore > 0 && $minutesUntil >= $reminderBefore - 1 && $minutesUntil <= $reminderBefore) {
                $this->notifyOnce(
                    $todayNotifications, $push, $schedule, 'medication.reminder_before',
                    '💊 Recordatorio de toma',
                    "En {$reminderBefore} minutos debes tomar {$schedule->medication->name} — {$schedule->medication->dosage}"
                );
            } elseif ($minutesUntil >= -1 && $minutesUntil <= 0) {
                $this->notifyOnce(
                    $todayNotifications, $push, $schedule, 'medication.reminder_now',
                    '💊 Es hora de tomar tu medicamento',
                    "Es hora de tomar {$schedule->medication->name} — {$schedule->medication->dosage}"
                );
            } elseif ($minutesUntil <= -30) {
                $this->notifyOnce(
                    $todayNotifications, $push, $schedule, 'medication.overdue',
                    '⚠️ Toma pendiente de registrar',
                    "No registraste la toma de {$schedule->medication->name} programada a las {$scheduledAt->format('H:i')}",
                    'warning'
                );
            }

            $this->checkLowStock($todayNotifications, $push, $schedule);
        }
    }

    private function notifyOnce(
        Collection $todayNotifications,
        PushNotificationService $push,
        MedicationSchedule $schedule,
        string $type,
        string $title,
        string $body,
        string $priority = 'info'
    ): void {
        $alreadySent = $todayNotifications->contains(
            fn (Notification $n) => $n->type === $type && ($n->data['medication_schedule_id'] ?? null) === $schedule->id
        );

        if ($alreadySent) {
            return;
        }

        $notification = Notification::create([
            'user_id' => $schedule->user_id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => ['medication_id' => $schedule->medication_id, 'medication_schedule_id' => $schedule->id],
            'priority' => $priority,
        ]);

        $todayNotifications->push($notification);

        $push->sendToUser($schedule->user, [
            'title' => $title,
            'body' => $body,
            'data' => ['medication_schedule_id' => $schedule->id],
        ]);
    }

    private function checkLowStock(Collection $todayNotifications, PushNotificationService $push, MedicationSchedule $schedule): void
    {
        $medication = $schedule->medication;

        if (! $medication->quantity || $medication->remaining_doses === null) {
            return;
        }

        if ($medication->remaining_doses > $medication->low_stock_alert_doses) {
            return;
        }

        $this->notifyOnce(
            $todayNotifications, $push, $schedule, 'medication.low_stock',
            '⚠️ Pocas dosis restantes',
            "Quedan pocas dosis de {$medication->name} — considera solicitar la renovación",
            'warning'
        );
    }
}
