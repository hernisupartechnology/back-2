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
        // 'America/Bogota' explícito (no el default de app.timezone=UTC): "hoy" y los
        // horarios de toma (time_of_day, ej. "08:00") son conceptos de calendario/reloj
        // del usuario colombiano, no de UTC — combinarlos con la fecha UTC correría las
        // tomas 5 horas. app.timezone se mantiene en UTC a propósito para que Eloquent
        // no desincronice los datetimes ya guardados (ver comentario en config/app.php).
        $today = Carbon::today('America/Bogota');
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

        // $today está en America/Bogota, pero scheduled_datetime se guarda/lee en UTC
        // (app.timezone=UTC) — comparar/emparejar por fecha o por string formateado
        // debe normalizarse SIEMPRE al mismo timezone en ambos lados, o dos Carbon que
        // representan el mismo instante producen strings distintos (ej. "07:00" en
        // Bogota vs "12:00" en UTC) y el emparejamiento falla en silencio: una toma ya
        // registrada se seguía mostrando como pendiente porque nunca "encontraba" su log.
        $existingLogs = MedicationIntakeLog::whereIn('user_id', $userIds)
            ->whereBetween('scheduled_datetime', [$today->copy()->utc(), $today->copy()->addDay()->utc()])
            ->get()
            ->keyBy(fn (MedicationIntakeLog $log) => $log->medication_id.'|'.$log->scheduled_datetime->copy()->utc()->format('Y-m-d H:i:s'));

        return $schedules
            ->map(function (MedicationSchedule $schedule) use ($today, $existingLogs, $now) {
                $scheduledDatetime = $today->copy()->setTimeFromTimeString((string) $schedule->time_of_day);
                $key = $schedule->medication_id.'|'.$scheduledDatetime->copy()->utc()->format('Y-m-d H:i:s');
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
        // scheduled_datetime se lee en UTC (app.timezone) — agrupar con toDateString()
        // sin convertir antes usa el día calendario de UTC, no el de Bogotá. Como
        // Bogotá va 5h detrás de UTC, cualquier toma programada después de las 7pm
        // hora Colombia ya cae en el día UTC siguiente, y aparecía "adelantada" un
        // día en la racha/calendario. Hay que convertir a America/Bogota ANTES de
        // sacar la fecha.
        $byDay = MedicationIntakeLog::where('medication_id', $medication->id)
            ->orderByDesc('scheduled_datetime')
            ->get()
            ->groupBy(fn (MedicationIntakeLog $log) => $log->scheduled_datetime->copy()->timezone('America/Bogota')->toDateString());

        $streak = 0;
        $cursor = Carbon::today('America/Bogota');

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
        // whereYear/whereMonth filtran contra la columna cruda en UTC — el mismo
        // desfase de "un día adelantado" de currentStreak() aplicaría también acá
        // (y en el límite de mes, una toma de la última noche del mes podría quedar
        // fuera del mes que pidió el usuario). Se calcula el rango del mes en
        // America/Bogota y se convierte a UTC para la consulta; el agrupamiento por
        // día también convierte antes de leer la fecha.
        $start = Carbon::create($year, $month, 1, 0, 0, 0, 'America/Bogota');
        $end = $start->copy()->addMonthNoOverflow();

        $byDay = MedicationIntakeLog::where('medication_id', $medication->id)
            ->whereBetween('scheduled_datetime', [$start->copy()->utc(), $end->copy()->utc()])
            ->get()
            ->groupBy(fn (MedicationIntakeLog $log) => $log->scheduled_datetime->copy()->timezone('America/Bogota')->toDateString());

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
