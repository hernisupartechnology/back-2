<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesVisibleUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Medication\StoreMedicationRequest;
use App\Http\Requests\Medication\UpdateMedicationRequest;
use App\Http\Resources\MedicationResource;
use App\Models\ActivityLog;
use App\Models\Medication;
use App\Models\MedicationRenewal;
use App\Models\MedicationSchedule;
use App\Models\User;
use App\Services\TrafficLightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Controlador de medicamentos — flujo completo desde sin_orden hasta
 * completado, renovaciones de medicamentos crónicos y configuración de
 * horarios de toma.
 */
class MedicationController extends Controller
{
    use ScopesVisibleUsers;

    public function __construct(private readonly TrafficLightService $trafficLight) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $visibleIds = $this->visibleUserIds($user);

        if ($request->filled('userId')) {
            abort_unless(in_array((int) $request->integer('userId'), $visibleIds, true), 403, 'No tienes acceso al historial de este miembro.');
            $visibleIds = [$request->integer('userId')];
        }

        $query = Medication::query()->whereIn('user_id', $visibleIds)->with('patient');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('recurring')) {
            $query->where('is_recurring', $request->boolean('recurring'));
        }

        $medications = $query->orderByDesc('created_at')->get();

        return MedicationResource::collection($medications);
    }

    public function store(StoreMedicationRequest $request): JsonResponse
    {
        $user = $request->user();
        $patient = User::findOrFail($request->integer('user_id'));
        $this->authorize('create', [Medication::class, $patient]);

        $data = $request->validated();

        $medication = DB::transaction(function () use ($data, $user, $patient) {
            $medication = Medication::create([
                'appointment_id' => $data['appointment_id'] ?? null,
                'household_id' => $user->household_id,
                'user_id' => $patient->id,
                'registered_by' => $user->id,
                'name' => $data['name'],
                'active_ingredient' => $data['active_ingredient'] ?? null,
                'presentation' => $data['presentation'] ?? null,
                'dosage' => $data['dosage'],
                'frequency' => $data['frequency'],
                'duration_days' => $data['duration_days'] ?? null,
                'quantity' => $data['quantity'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'is_recurring' => $data['is_recurring'] ?? false,
                'recurrence_days' => $data['recurrence_days'] ?? null,
                'alert_days_before' => $data['alert_days_before'] ?? 10,
                'status' => $data['status'] ?? 'con_orden',
                'authorization_number' => $data['authorization_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'track_intake' => $data['track_intake'] ?? false,
                'intake_quantity_per_dose' => $data['intake_quantity_per_dose'] ?? null,
                'low_stock_alert_doses' => $data['low_stock_alert_doses'] ?? 5,
            ]);

            if (! empty($data['track_intake']) && ! empty($data['schedules'])) {
                foreach ($data['schedules'] as $schedule) {
                    MedicationSchedule::create([
                        'medication_id' => $medication->id,
                        'user_id' => $patient->id,
                        'time_of_day' => $schedule['time_of_day'],
                        'label' => $schedule['label'] ?? null,
                        'days_of_week' => $schedule['days_of_week'] ?? null,
                        'reminder_minutes_before' => $schedule['reminder_minutes_before'] ?? 5,
                    ]);
                }
            }

            return $medication;
        });

        $this->logActivity($request, 'medication.created', $medication);

        return (new MedicationResource($medication->load(['patient', 'schedules'])))
            ->additional(['message' => '¡Medicamento registrado correctamente!'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Medicamentos recurrentes con alerta de renovación activa (rojo/amarillo),
     * para la sección fija "ALERTAS DE RENOVACIÓN" del tab de medicamentos.
     */
    public function alerts(Request $request): JsonResponse
    {
        $visibleIds = $this->visibleUserIds($request->user());

        $medications = Medication::whereIn('user_id', $visibleIds)
            ->where('is_recurring', true)
            ->whereNotIn('status', ['completado', 'suspendido'])
            ->with('patient')
            ->get()
            ->filter(function (Medication $m) {
                $tl = $this->trafficLight->forMedicationRenewal($m);

                return $tl && in_array($tl['level'], ['red', 'yellow'], true);
            })
            ->sortBy(fn (Medication $m) => $this->trafficLight->forMedicationRenewal($m)['level'] === 'red' ? 0 : 1)
            ->values();

        return response()->json([
            'alerts' => MedicationResource::collection($medications),
        ]);
    }

    public function show(int $id): MedicationResource
    {
        $medication = Medication::with(['patient', 'schedules', 'renewals'])->findOrFail($id);
        $this->authorize('view', $medication);

        return new MedicationResource($medication);
    }

    public function update(UpdateMedicationRequest $request, int $id): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('update', $medication);

        $medication->update($request->validated());

        return (new MedicationResource($medication->fresh(['patient', 'schedules'])))
            ->additional(['message' => 'Medicamento actualizado correctamente.'])
            ->response();
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('update', $medication);

        $targetStatus = $request->string('status')->toString();

        abort_if($targetStatus === 'vencido', 422, 'El estado "vencido" se asigna automáticamente cuando expira la autorización.');

        $data = $request->validate([
            'status' => 'required|in:con_orden,en_autorizacion,autorizado,negado,reclamado,en_uso,completado,suspendido',
            'authorization_number' => [Rule::requiredIf($targetStatus === 'autorizado'), 'nullable', 'string', 'max:100'],
            'authorization_date' => [Rule::requiredIf($targetStatus === 'autorizado'), 'nullable', 'date'],
            'denied_reason' => [Rule::requiredIf($targetStatus === 'negado'), 'nullable', 'string'],
            'claimed_date' => [Rule::requiredIf($targetStatus === 'reclamado'), 'nullable', 'date'],
            'start_date' => [Rule::requiredIf($targetStatus === 'en_uso'), 'nullable', 'date'],
            'notes' => [Rule::requiredIf($targetStatus === 'suspendido'), 'nullable', 'string'],
        ]);

        $previousStatus = $medication->status;
        $updates = ['status' => $data['status']];

        if ($targetStatus === 'autorizado') {
            $updates['authorization_number'] = $data['authorization_number'];
            $updates['authorization_date'] = $data['authorization_date'];
        } elseif ($targetStatus === 'negado') {
            $updates['denied_reason'] = $data['denied_reason'];
        } elseif ($targetStatus === 'reclamado') {
            $updates['claimed_date'] = $data['claimed_date'];
        } elseif ($targetStatus === 'en_uso') {
            $updates['start_date'] = $data['start_date'];
        } elseif ($targetStatus === 'suspendido') {
            $updates['notes'] = $data['notes'];
        }

        $medication->update($updates);

        $this->logActivity($request, 'medication.status_changed', $medication, $previousStatus, $data['status']);

        return (new MedicationResource($medication->fresh(['patient', 'schedules'])))
            ->additional(['message' => 'Estado del medicamento actualizado.'])
            ->response();
    }

    /** Inicia una nueva renovación — crea el siguiente registro en medication_renewals. */
    public function renew(Request $request, int $id): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('update', $medication);

        abort_unless($medication->is_recurring, 422, 'Este medicamento no es recurrente.');

        $lastNumber = $medication->renewals()->max('renewal_number') ?? 0;
        $periodStart = now()->toDateString();
        $periodEnd = now()->addDays($medication->recurrence_days ?? 30)->toDateString();

        $renewal = MedicationRenewal::create([
            'medication_id' => $medication->id,
            'renewal_number' => $lastNumber + 1,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'pendiente',
        ]);

        $this->logActivity($request, 'medication.renewal_started', $medication);

        return response()->json([
            'message' => 'Renovación iniciada correctamente.',
            'renewal' => $renewal,
        ], 201);
    }

    public function renewals(int $id): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('view', $medication);

        return response()->json(['renewals' => $medication->renewals()->get()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('delete', $medication);

        $medication->delete();

        return response()->json(['message' => 'Medicamento eliminado correctamente.']);
    }

    private function logActivity(
        Request $request,
        string $action,
        Medication $medication,
        ?string $previousStatus = null,
        ?string $newStatus = null
    ): void {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'model_type' => Medication::class,
            'model_id' => $medication->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'ip_address' => $request->ip(),
        ]);
    }
}
