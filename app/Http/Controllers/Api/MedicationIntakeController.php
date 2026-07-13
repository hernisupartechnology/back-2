<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesVisibleUsers;
use App\Http\Controllers\Controller;
use App\Models\Medication;
use App\Models\MedicationIntakeLog;
use App\Services\MedicationIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Controlador de registro de tomas — panel "Tomas de hoy", acciones rápidas
 * (tomado/omitir/posponer) y estadísticas de adherencia.
 */
class MedicationIntakeController extends Controller
{
    use ScopesVisibleUsers;

    public function __construct(private readonly MedicationIntakeService $intakeService) {}

    /** Todas las tomas de hoy (reales + virtuales) visibles para el usuario autenticado. */
    public function todayIntakes(Request $request): JsonResponse
    {
        $user = $request->user();
        $visibleIds = $this->visibleUserIds($user);

        if ($request->filled('userId')) {
            abort_unless(in_array((int) $request->integer('userId'), $visibleIds, true), 403, 'No tienes acceso al historial de este miembro.');
            $visibleIds = [$request->integer('userId')];
        }

        return response()->json(['intakes' => $this->intakeService->todayIntakesFor($visibleIds)]);
    }

    public function index(Request $request, int $id): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('view', $medication);

        $query = $medication->intakeLogs()->orderByDesc('scheduled_datetime');

        if ($request->filled('from')) {
            $query->where('scheduled_datetime', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('scheduled_datetime', '<=', $request->date('to'));
        }

        // Un medicamento crónico de años acumula miles de logs (uno por
        // toma programada) — sin `from`/`to` ni `limit`, esto no tiene tope.
        // Nadie en el frontend llama este endpoint todavía (el calendario de
        // adherencia usa /medications/{id}/adherence, ya con agregados SQL),
        // pero se deja acotado por defecto para cualquier futuro consumidor.
        $query->limit($request->filled('limit') ? min(500, $request->integer('limit')) : 200);

        return response()->json(['intake_logs' => $query->get()]);
    }

    /** Registra una toma — crea o actualiza el log (idempotente por el índice único). */
    public function store(Request $request, int $id): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('update', $medication);

        $data = $request->validate([
            'medication_schedule_id' => 'nullable|integer|exists:medication_schedules,id',
            'scheduled_datetime' => 'required|date',
            'taken_at' => 'nullable|date',
            'status' => 'nullable|in:tomado,omitido,atrasado,pospuesto',
            'dose_taken' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        // Normalizar a "Y-m-d H:i:s" — el índice único compara contra el valor
        // exacto guardado en BD, y el frontend puede enviar formato ISO (con "T"/"Z").
        $scheduledAt = Carbon::parse($data['scheduled_datetime'])->format('Y-m-d H:i:s');

        $log = MedicationIntakeLog::updateOrCreate(
            [
                'medication_id' => $medication->id,
                'scheduled_datetime' => $scheduledAt,
                'user_id' => $medication->user_id,
            ],
            [
                'medication_schedule_id' => $data['medication_schedule_id'] ?? null,
                'registered_by' => $request->user()->id,
                'taken_at' => $data['taken_at'] ?? null,
                'status' => $data['status'] ?? 'tomado',
                'dose_taken' => $data['dose_taken'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Toma registrada correctamente.',
            'intake_log' => $log->fresh(),
        ], 201);
    }

    public function update(Request $request, int $id, int $logId): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('update', $medication);

        $log = $medication->intakeLogs()->findOrFail($logId);

        $data = $request->validate([
            'taken_at' => 'nullable|date',
            'status' => 'nullable|in:tomado,omitido,atrasado,pospuesto',
            'dose_taken' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $log->update($data);

        return response()->json([
            'message' => 'Toma actualizada correctamente.',
            'intake_log' => $log->fresh(),
        ]);
    }

    /** Marca como tomado ahora mismo — usado por el botón "✓ Tomado" de una toma ya registrada. */
    public function quickTake(Request $request, int $logId): JsonResponse
    {
        $log = MedicationIntakeLog::findOrFail($logId);
        $medication = Medication::findOrFail($log->medication_id);
        $this->authorize('update', $medication);

        $log->update([
            'taken_at' => now(),
            'status' => 'tomado',
        ]);

        return response()->json([
            'message' => '¡Toma registrada!',
            'intake_log' => $log->fresh(),
        ]);
    }

    /** Pospone la alerta — el usuario tocó "Posponer 15/30/60 min". */
    public function snooze(Request $request, int $logId): JsonResponse
    {
        $log = MedicationIntakeLog::findOrFail($logId);
        $medication = Medication::findOrFail($log->medication_id);
        $this->authorize('update', $medication);

        $minutes = $request->validate(['minutes' => 'required|integer|in:15,30,60'])['minutes'];

        $log->update([
            'status' => 'pospuesto',
            'notes' => trim(($log->notes ? $log->notes.' — ' : '')."Pospuesto {$minutes} min"),
        ]);

        return response()->json([
            'message' => "Recordatorio pospuesto {$minutes} minutos.",
            'intake_log' => $log->fresh(),
        ]);
    }

    public function adherence(Request $request, int $id): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('view', $medication);

        $month = $request->integer('month') ?: null;
        $year = $request->integer('year') ?: null;

        return response()->json($this->intakeService->adherenceStats($medication, $month, $year));
    }
}
