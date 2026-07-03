<?php

namespace App\Services;

use App\Models\Medication;
use App\Models\MedicationIntakeLog;
use App\Models\MedicationSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula las tomas de medicamentos del día (combinando horarios activos con
 * los registros ya existentes) y las estadísticas de adherencia.
 *
 * Una "toma" solo existe como fila en medication_intake_logs una vez que el
 * usuario la registra (tomado/omitido/pospuesto) o el job de recordatorios
 * la crea. Antes de eso es una entrada "virtual" (id = null) derivada de
 * medication_schedules, con un display_status calculado en el momento.
 */
class MedicationIntakeService
{
    /** @param  array<int>  $userIds */
    public function todayIntakesFor(array $userIds): Collection
    {
        $today = Carbon::today();
        $isoWeekday = $today->dayOfWeekIso; // 1=lunes ... 7=domingo
        $now = Carbon::now();

        $schedules = MedicationSchedule::whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->whereHas('medication', fn ($q) => $q->where('status', 'en_uso')->where('track_intake', true))
            ->with(['medication:id,name,dosage,presentation', 'user:id,name,avatar'])
            ->get()
            ->filter(fn (MedicationSchedule $s) => $s->appliesOnDay($isoWeekday));

        if ($schedules->isEmpty()) {
            return collect();
        }

        $existingLogs = MedicationIntakeLog::whereIn('user_id', $userIds)
            ->whereDate('scheduled_datetime', $today->toDateString())
            ->get()
            ->keyBy(fn (MedicationIntakeLog $log) => $log->medication_id.'|'.$log->scheduled_datetime->format('Y-m-d H:i:s'));

        return $schedules
            ->map(function (MedicationSchedule $schedule) use ($today, $existingLogs, $now) {
                $scheduledDatetime = $today->copy()->setTimeFromTimeString((string) $schedule->time_of_day);
                $key = $schedule->medication_id.'|'.$scheduledDatetime->format('Y-m-d H:i:s');
                $log = $existingLogs->get($key);

                return [
                    'id' => $log?->id,
                    'medication_id' => $schedule->medication_id,
                    'medication_schedule_id' => $schedule->id,
                    'user_id' => $schedule->user_id,
                    'scheduled_datetime' => $scheduledDatetime->toISOString(),
                    'taken_at' => $log?->taken_at?->toISOString(),
                    'status' => $log?->status,
                    'display_status' => $log
                        ? $this->displayStatusForLog($log, $now)
                        : $this->displayStatusForVirtual($scheduledDatetime, $now),
                    'delay_minutes' => $log?->delay_minutes,
                    'label' => $schedule->label,
                    'medication' => $schedule->medication ? [
                        'id' => $schedule->medication->id,
                        'name' => $schedule->medication->name,
                        'dosage' => $schedule->medication->dosage,
                        'presentation' => $schedule->medication->presentation,
                    ] : null,
                    'patient' => $schedule->user ? [
                        'id' => $schedule->user->id,
                        'name' => $schedule->user->name,
                        'avatar' => $schedule->user->avatar,
                    ] : null,
                ];
            })
            ->sortBy('scheduled_datetime')
            ->values();
    }

    private function displayStatusForVirtual(Carbon $scheduled, Carbon $now): string
    {
        $diffMinutes = $scheduled->diffInMinutes($now, false);

        if ($diffMinutes < 0) {
            return 'pendiente';
        }
        if ($diffMinutes <= 30) {
            return 'por_tomar';
        }

        return 'atrasado';
    }

    private function displayStatusForLog(MedicationIntakeLog $log, Carbon $now): string
    {
        if ($log->status === 'omitido') {
            return 'omitido';
        }
        if ($log->status === 'pospuesto') {
            return 'pospuesto';
        }
        if ($log->taken_at) {
            return abs($log->delay_minutes ?? 0) <= 30 ? 'tomado_a_tiempo' : 'tomado_tarde';
        }

        return $this->displayStatusForVirtual($log->scheduled_datetime, $now);
    }

    /** @return array<string, mixed> */
    public function adherenceStats(Medication $medication, ?int $month = null, ?int $year = null): array
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $logs7d = MedicationIntakeLog::where('medication_id', $medication->id)
            ->where('scheduled_datetime', '>=', now()->subDays(7))
            ->get();

        $logsMonth = MedicationIntakeLog::where('medication_id', $medication->id)
            ->whereYear('scheduled_datetime', $year)
            ->whereMonth('scheduled_datetime', $month)
            ->get();

        $logsAll = MedicationIntakeLog::where('medication_id', $medication->id)->get();

        return [
            'adherence_7d' => $this->adherencePercent($logs7d),
            'adherence_month' => $this->adherencePercent($logsMonth),
            'adherence_total' => $this->adherencePercent($logsAll),
            'counts' => [
                'tomado' => $logsAll->where('status', 'tomado')->count(),
                'atrasado' => $logsAll->where('status', 'atrasado')->count(),
                'omitido' => $logsAll->where('status', 'omitido')->count(),
                'pospuesto' => $logsAll->where('status', 'pospuesto')->count(),
            ],
            'current_streak' => $this->currentStreak($medication),
            'calendar' => $this->monthCalendar($medication, $month, $year),
        ];
    }

    private function adherencePercent(Collection $logs): float
    {
        if ($logs->isEmpty()) {
            return 0.0;
        }

        $taken = $logs->whereIn('status', ['tomado', 'atrasado'])->count();

        return round(($taken / $logs->count()) * 100, 1);
    }

    /** Días consecutivos hacia atrás sin ninguna toma omitida ese día. */
    private function currentStreak(Medication $medication): int
    {
        $byDay = MedicationIntakeLog::where('medication_id', $medication->id)
            ->orderByDesc('scheduled_datetime')
            ->get()
            ->groupBy(fn (MedicationIntakeLog $log) => $log->scheduled_datetime->toDateString());

        $streak = 0;
        $cursor = Carbon::today();

        if (! $byDay->has($cursor->toDateString())) {
            $cursor->subDay();
        }

        while ($byDay->has($cursor->toDateString())) {
            $dayLogs = $byDay->get($cursor->toDateString());
            if ($dayLogs->contains(fn (MedicationIntakeLog $l) => $l->status === 'omitido')) {
                break;
            }
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    /** Calendario de adherencia del mes — un punto por día con tomas registradas. */
    private function monthCalendar(Medication $medication, int $month, int $year): array
    {
        $byDay = MedicationIntakeLog::where('medication_id', $medication->id)
            ->whereYear('scheduled_datetime', $year)
            ->whereMonth('scheduled_datetime', $month)
            ->get()
            ->groupBy(fn (MedicationIntakeLog $log) => $log->scheduled_datetime->toDateString());

        $calendar = [];

        foreach ($byDay as $date => $dayLogs) {
            $total = $dayLogs->count();
            $taken = $dayLogs->whereIn('status', ['tomado', 'atrasado'])->count();
            $pct = $total > 0 ? ($taken / $total) * 100 : 0;

            $calendar[] = [
                'date' => $date,
                'percentage' => round($pct, 1),
                'level' => $pct >= 100 ? 'green' : ($pct >= 50 ? 'yellow' : 'red'),
                'logs' => $dayLogs->values(),
            ];
        }

        return $calendar;
    }
}
