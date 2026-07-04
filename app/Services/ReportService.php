<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Exam;
use App\Models\Household;
use App\Models\MedicalLeave;
use App\Models\Medication;
use App\Models\Referral;
use App\Models\ReportHistory;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera los 4 tipos de reporte (individual, hogar, alertas, incapacidades)
 * en PDF (DomPDF) o Excel (PhpSpreadsheet), y mantiene el historial de los
 * últimos 10 reportes por usuario en report_history.
 */
class ReportService
{
    private const MAX_HISTORY_PER_USER = 10;

    public function __construct(private readonly TrafficLightService $trafficLight) {}

    /** @param  array<string, mixed>  $params */
    public function generate(User $user, string $reportType, string $format, array $params): ReportHistory
    {
        $history = ReportHistory::create([
            'user_id' => $user->id,
            'report_type' => $reportType,
            'format' => $format,
            'parameters' => $params,
            'status' => 'generando',
        ]);

        try {
            $content = match ($reportType) {
                'individual' => $this->individual($params, $format),
                'household' => $this->household($user, $params, $format),
                'alerts' => $this->alerts($user, $format),
                'leaves' => $this->leaves($user, $params, $format),
                default => throw new \InvalidArgumentException('Tipo de reporte inválido.'),
            };

            $extension = $format === 'pdf' ? 'pdf' : 'xlsx';
            $fileName = "{$reportType}_".now()->format('Ymd_His').".{$extension}";
            $path = "reports/{$user->id}/{$fileName}";

            Storage::disk('local')->put($path, $content);

            $history->update([
                'file_path' => $path,
                'file_name' => $fileName,
                'file_size' => strlen($content),
                'status' => 'completado',
                'generated_at' => now(),
            ]);

            $this->pruneOldReports($user);
        } catch (\Throwable $e) {
            $history->update(['status' => 'error', 'error_message' => $e->getMessage()]);
            throw $e;
        }

        return $history->fresh();
    }

    // ── A) Reporte individual ────────────────────────────────────────────

    private function individual(array $params, string $format): string
    {
        $member = User::findOrFail($params['user_id']);
        $sections = $params['sections'] ?? [];
        [$from, $to, $periodLabel] = $this->resolvePeriod($params);

        $data = $this->gatherMemberData($member, $from, $to);

        if ($format === 'pdf') {
            return Pdf::loadView('reports.individual', compact('member', 'sections', 'data', 'periodLabel'))->output();
        }

        return $this->buildExcel("Historial — {$member->name}", $this->memberDataToSheets($member->name, $data));
    }

    // ── B) Reporte del hogar ──────────────────────────────────────────────

    private function household(User $user, array $params, string $format): string
    {
        $household = Household::with('members')->findOrFail($user->household_id);
        $sections = $params['sections'] ?? [];
        [$from, $to, $periodLabel] = $this->resolvePeriod($params);

        $membersData = $household->members->map(fn (User $member) => [
            'member' => $member,
            'data' => $this->gatherMemberData($member, $from, $to),
        ]);

        if ($format === 'pdf') {
            return Pdf::loadView('reports.household', compact('household', 'sections', 'membersData', 'periodLabel'))->output();
        }

        $sheets = [];
        foreach ($membersData as $entry) {
            $sheets = [...$sheets, ...$this->memberDataToSheets($entry['member']->name, $entry['data'])];
        }

        return $this->buildExcel("Hogar — {$household->name}", $sheets);
    }

    // ── C) Reporte de alertas activas ────────────────────────────────────

    private function alerts(User $user, string $format): string
    {
        $household = Household::findOrFail($user->household_id);
        $memberIds = User::where('household_id', $household->id)->pluck('id')->all();
        $members = User::whereIn('id', $memberIds)->get(['id', 'name'])->keyBy('id');

        $alerts = collect();

        foreach (Appointment::whereIn('user_id', $memberIds)->whereNotIn('status', ['realizada', 'cancelada', 'no_asistio'])->get() as $a) {
            $tl = $this->trafficLight->forAppointment($a);
            if (in_array($tl['level'], ['red', 'yellow'], true)) {
                $alerts->push(['level' => $tl['level'], 'title' => "Cita de {$a->specialty}", 'description' => $tl['label'], 'member' => ['name' => $members->get($a->user_id)?->name]]);
            }
        }

        foreach (Medication::whereIn('user_id', $memberIds)->where('is_recurring', true)->whereNotIn('status', ['completado', 'suspendido'])->get() as $m) {
            $tl = $this->trafficLight->forMedicationRenewal($m);
            if ($tl && in_array($tl['level'], ['red', 'yellow'], true)) {
                $alerts->push(['level' => $tl['level'], 'title' => "Renovación de {$m->name}", 'description' => $tl['label'], 'member' => ['name' => $members->get($m->user_id)?->name]]);
            }
        }

        foreach (Referral::whereIn('user_id', $memberIds)->where('status', 'autorizada')->get() as $r) {
            $tl = $this->trafficLight->forReferral($r);
            if ($tl && in_array($tl['level'], ['red', 'yellow'], true)) {
                $alerts->push(['level' => $tl['level'], 'title' => "Remisión a {$r->specialty}", 'description' => $tl['label'], 'member' => ['name' => $members->get($r->user_id)?->name]]);
            }
        }

        $alerts = $alerts->sortBy(fn ($a) => $a['level'] === 'red' ? 0 : 1)->values()->all();
        $summary = [
            'red' => collect($alerts)->where('level', 'red')->count(),
            'yellow' => collect($alerts)->where('level', 'yellow')->count(),
            'blue' => 0,
        ];

        if ($format === 'pdf') {
            return Pdf::loadView('reports.alerts', compact('household', 'alerts', 'summary'))->output();
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Alertas');
        $sheet->fromArray(['Nivel', 'Miembro', 'Alerta', 'Detalle'], null, 'A1');
        $row = 2;
        foreach ($alerts as $a) {
            $sheet->fromArray([strtoupper($a['level']), $a['member']['name'] ?? '—', $a['title'], $a['description']], null, "A{$row}");
            $row++;
        }

        return $this->spreadsheetToString($spreadsheet);
    }

    // ── D) Reporte de incapacidades ──────────────────────────────────────

    private function leaves(User $user, array $params, string $format): string
    {
        $household = Household::findOrFail($user->household_id);
        $memberIds = User::where('household_id', $household->id)->pluck('id')->all();
        [$from, $to, $periodLabel] = $this->resolvePeriod($params);

        $query = MedicalLeave::whereIn('user_id', $memberIds)->with('patient')->orderBy('start_date');
        if ($from) {
            $query->where('start_date', '>=', $from);
        }
        if ($to) {
            $query->where('end_date', '<=', $to);
        }
        $leaves = $query->get();

        if ($format === 'pdf') {
            return Pdf::loadView('reports.leaves', compact('household', 'leaves', 'periodLabel'))->output();
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Incapacidades');
        $sheet->fromArray(['Miembro', 'Desde', 'Hasta', 'Días', 'Tipo', 'Diagnóstico', 'IPS'], null, 'A1');
        $row = 2;
        foreach ($leaves as $l) {
            $sheet->fromArray([
                $l->patient->name ?? '—', $l->start_date->format('Y-m-d'), $l->end_date->format('Y-m-d'),
                $l->total_days, $l->leave_type, $l->diagnosis, $l->ips_issued,
            ], null, "A{$row}");
            $row++;
        }

        return $this->spreadsheetToString($spreadsheet);
    }

    // ── Helpers compartidos ──────────────────────────────────────────────

    /** @return array<string, Collection> */
    private function gatherMemberData(User $member, ?Carbon $from, ?Carbon $to): array
    {
        $inPeriod = fn ($query, string $dateColumn) => (clone $query)
            ->when($from, fn ($q) => $q->where($dateColumn, '>=', $from))
            ->when($to, fn ($q) => $q->where($dateColumn, '<=', $to))
            ->get();

        return [
            'allergies' => $member->allergies()->where('is_active', true)->get(),
            'chronicConditions' => $member->chronicConditions()->where('is_active', true)->get(),
            'appointments' => $inPeriod($member->appointments()->with('doctor'), 'appointment_date'),
            'medications' => $inPeriod($member->medications(), 'created_at'),
            'exams' => $inPeriod($member->exams(), 'created_at'),
            'referrals' => $inPeriod($member->referrals(), 'created_at'),
            'leaves' => $inPeriod($member->medicalLeaves(), 'start_date'),
            'vaccinations' => $inPeriod($member->vaccinations(), 'application_date'),
            'vitalSigns' => $inPeriod($member->vitalSigns(), 'measurement_date'),
            'documents' => $inPeriod($member->medicalDocuments(), 'document_date'),
        ];
    }

    /** @return array{0: ?Carbon, 1: ?Carbon, 2: string} */
    private function resolvePeriod(array $params): array
    {
        if (! empty($params['from']) || ! empty($params['to'])) {
            $from = ! empty($params['from']) ? Carbon::parse($params['from'])->startOfDay() : null;
            $to = ! empty($params['to']) ? Carbon::parse($params['to'])->endOfDay() : null;
            $label = ($from?->format('d/m/Y') ?? 'inicio').' — '.($to?->format('d/m/Y') ?? 'hoy');

            return [$from, $to, $label];
        }

        if (($params['period'] ?? 'all') === 'month') {
            return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(), 'Mes actual ('.Carbon::now()->translatedFormat('F Y').')'];
        }

        if (($params['period'] ?? 'all') === 'year') {
            return [Carbon::now()->startOfYear(), Carbon::now()->endOfYear(), 'Año actual ('.Carbon::now()->year.')'];
        }

        return [null, null, 'Histórico completo'];
    }

    /** @return array<int, array{title: string, rows: array}> */
    private function memberDataToSheets(string $memberName, array $data): array
    {
        return [
            ['title' => mb_substr("{$memberName} - Citas", 0, 31), 'rows' => $data['appointments']->map(fn (Appointment $a) => [
                $a->specialty, $a->is_need ? 'Sin agendar' : optional($a->appointment_date)->format('Y-m-d H:i'), $a->status, $a->diagnosis,
            ])->all()],
            ['title' => mb_substr("{$memberName} - Medicamentos", 0, 31), 'rows' => $data['medications']->map(fn (Medication $m) => [
                $m->name, $m->dosage, $m->frequency, $m->status,
            ])->all()],
            ['title' => mb_substr("{$memberName} - Examenes", 0, 31), 'rows' => $data['exams']->map(fn (Exam $e) => [
                $e->name, $e->exam_type, $e->status, $e->result_summary,
            ])->all()],
        ];
    }

    /** @param  array<int, array{title: string, rows: array}>  $sheetsData */
    private function buildExcel(string $title, array $sheetsData): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheetsData as $i => $sheetData) {
            $sheet = $spreadsheet->createSheet($i);
            $sheet->setTitle($sheetData['title'] ?: "Hoja {$i}");
            foreach ($sheetData['rows'] as $rowIndex => $row) {
                $sheet->fromArray($row, null, 'A'.($rowIndex + 1));
            }
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $spreadsheet->createSheet()->setTitle($title);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $this->spreadsheetToString($spreadsheet);
    }

    private function spreadsheetToString(Spreadsheet $spreadsheet): string
    {
        $writer = new Xlsx($spreadsheet);
        $tmpPath = tempnam(sys_get_temp_dir(), 'uparvital_report_');
        $writer->save($tmpPath);
        $content = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $content;
    }

    private function pruneOldReports(User $user): void
    {
        ReportHistory::where('user_id', $user->id)
            ->where('status', 'completado')
            ->orderByDesc('id')
            ->skip(self::MAX_HISTORY_PER_USER)
            ->take(PHP_INT_MAX)
            ->get()
            ->each(function (ReportHistory $old) {
                if ($old->file_path) {
                    Storage::disk('local')->delete($old->file_path);
                }
                $old->delete();
            });
    }
}
