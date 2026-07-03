<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesVisibleUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\AppointmentRecurrenceLog;
use App\Models\User;
use App\Services\AppointmentRecurrenceService;
use App\Services\TrafficLightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Controlador de citas médicas y necesidades sin agendar.
 * Cubre el flujo completo: necesidad → programada → confirmada → realizada,
 * cancelaciones, reprogramaciones y cadenas de citas recurrentes.
 */
class AppointmentController extends Controller
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

        $query = Appointment::query()
            ->whereIn('user_id', $visibleIds)
            ->with(['doctor', 'patient']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('recurring')) {
            $query->where('is_recurring', $request->boolean('recurring'));
        }
        if ($request->filled('isNeed')) {
            $query->where('is_need', $request->boolean('isNeed'));
        }
        if ($request->filled('from')) {
            $query->where(fn ($q) => $q->whereNull('appointment_date')->orWhere('appointment_date', '>=', $request->date('from')));
        }
        if ($request->filled('to')) {
            $query->where(fn ($q) => $q->whereNull('appointment_date')->orWhere('appointment_date', '<=', $request->date('to')));
        }

        $appointments = $query->orderByRaw('appointment_date IS NULL, appointment_date ASC')->get();

        if ($request->filled('trafficLight')) {
            $level = $request->string('trafficLight')->toString();
            $appointments = $appointments
                ->filter(fn (Appointment $a) => $this->trafficLight->forAppointment($a)['level'] === $level)
                ->values();
        }

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $user = $request->user();
        $patient = User::findOrFail($request->integer('user_id'));
        $this->authorize('create', [Appointment::class, $patient]);

        $data = $request->validated();
        $isNeed = (bool) $data['is_need'];

        $appointment = Appointment::create([
            'household_id' => $user->household_id,
            'user_id' => $patient->id,
            'registered_by' => $user->id,
            'doctor_id' => $data['doctor_id'] ?? null,
            'doctor_name_free' => $data['doctor_name_free'] ?? null,
            'specialty' => $data['specialty'],
            'appointment_type' => $data['appointment_type'] ?? 'consulta',
            'ips' => $data['ips'] ?? null,
            'address' => $data['address'] ?? null,
            'is_need' => $isNeed,
            'need_reason' => $data['need_reason'] ?? null,
            'need_urgency' => $data['need_urgency'] ?? 'rutina',
            'need_registered_date' => $isNeed ? now()->toDateString() : null,
            'max_days_to_schedule' => $data['max_days_to_schedule'] ?? 30,
            'alert_days_before_scheduling' => $data['alert_days_before_scheduling'] ?? 10,
            'appointment_date' => $isNeed ? null : $data['appointment_date'],
            'reason' => $data['reason'] ?? null,
            'status' => $isNeed ? 'necesidad' : 'programada',
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurrence_type' => $data['recurrence_type'] ?? null,
            'alert_days_before_appointment' => $data['alert_days_before_appointment'] ?? 3,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logActivity($request, 'appointment.created', $appointment);

        return (new AppointmentResource($appointment->load(['doctor', 'patient'])))
            ->additional(['message' => $isNeed ? 'Necesidad registrada correctamente.' : '¡Cita registrada correctamente!'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): AppointmentResource
    {
        $appointment = Appointment::with(['doctor', 'patient', 'medications', 'exams'])->findOrFail($id);
        $this->authorize('view', $appointment);

        return new AppointmentResource($appointment);
    }

    public function update(UpdateAppointmentRequest $request, int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorize('update', $appointment);

        $appointment->update($request->validated());

        return (new AppointmentResource($appointment->fresh(['doctor', 'patient'])))
            ->additional(['message' => 'Cita actualizada correctamente.'])
            ->response();
    }

    /**
     * Cambia el estado de una cita, exigiendo los datos adicionales según la transición.
     */
    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorize('update', $appointment);

        $targetStatus = $request->string('status')->toString();

        $data = $request->validate([
            'status' => 'required|in:programada,confirmada,realizada,cancelada,reprogramada,no_asistio',
            'appointment_date' => [Rule::requiredIf($targetStatus === 'programada'), 'nullable', 'date'],
            'diagnosis' => 'nullable|string',
            'next_appointment_date' => 'nullable|date',
            'next_appointment_notes' => 'nullable|string|max:255',
            'next_appointment_specialty' => 'nullable|string|max:255',
            'cancelled_reason' => [Rule::requiredIf($targetStatus === 'cancelada'), 'nullable', 'string'],
            'cancelled_by' => [Rule::requiredIf($targetStatus === 'cancelada'), 'nullable', 'in:paciente,ips,eps'],
            'notes' => 'nullable|string',
        ]);

        $previousStatus = $appointment->status;
        $updates = ['status' => $data['status']];

        if ($data['status'] === 'programada' && ! empty($data['appointment_date'])) {
            $updates['appointment_date'] = $data['appointment_date'];
        }

        if ($data['status'] === 'realizada') {
            $updates['diagnosis'] = $data['diagnosis'] ?? $appointment->diagnosis;
            if (! empty($data['next_appointment_date'])) {
                $updates['next_appointment_date'] = $data['next_appointment_date'];
                $updates['next_appointment_notes'] = $data['next_appointment_notes'] ?? null;
                $updates['next_appointment_specialty'] = $data['next_appointment_specialty'] ?? null;
            }
        }

        if ($data['status'] === 'cancelada') {
            $updates['cancelled_reason'] = $data['cancelled_reason'];
            $updates['cancelled_by'] = $data['cancelled_by'];
        }

        if (! empty($data['notes'])) {
            $updates['notes'] = $data['notes'];
        }

        $appointment->update($updates);

        $this->logActivity($request, 'appointment.status_changed', $appointment, $previousStatus, $data['status']);

        return (new AppointmentResource($appointment->fresh(['doctor', 'patient'])))
            ->additional(['message' => 'Estado de la cita actualizado.'])
            ->response();
    }

    /**
     * Convierte una necesidad sin fecha en una cita programada.
     */
    public function scheduleNeed(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorize('update', $appointment);

        abort_unless($appointment->is_need, 422, 'Esta cita ya está agendada.');

        $data = $request->validate([
            'appointment_date' => 'required|date',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'doctor_name_free' => 'nullable|string|max:255',
            'ips' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'appointment_type' => 'nullable|in:consulta,control,urgencias,domiciliaria,telemedicina',
        ]);

        $appointment->update([
            ...$data,
            'is_need' => false,
            'status' => 'programada',
        ]);

        $this->logActivity($request, 'appointment.scheduled', $appointment, 'necesidad', 'programada');

        return (new AppointmentResource($appointment->fresh(['doctor', 'patient'])))
            ->additional(['message' => '¡Cita agendada exitosamente!'])
            ->response();
    }

    /**
     * Genera manualmente la siguiente cita de una cadena recurrente
     * (respaldo para casos que el Observer no haya cubierto automáticamente).
     */
    public function generateNext(int $id, AppointmentRecurrenceService $recurrenceService): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorize('view', $appointment);

        abort_unless(
            $recurrenceService->shouldGenerateNext($appointment),
            422,
            'Esta cita no requiere generar la siguiente ocurrencia.'
        );

        $siguiente = $recurrenceService->generateNext($appointment);

        return (new AppointmentResource($siguiente->load(['doctor', 'patient'])))
            ->additional(['message' => 'Se generó la siguiente cita de la cadena recurrente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function recurrenceLog(int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorize('view', $appointment);

        $parentId = $appointment->parent_appointment_id ?? $appointment->id;

        $log = AppointmentRecurrenceLog::where('parent_appointment_id', $parentId)
            ->with('appointment')
            ->orderBy('recurrence_number')
            ->get();

        return response()->json(['recurrence_log' => $log]);
    }

    public function destroy(int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorize('delete', $appointment);

        $appointment->delete();

        return response()->json(['message' => 'Cita eliminada correctamente.']);
    }

    private function logActivity(
        Request $request,
        string $action,
        Appointment $appointment,
        ?string $previousStatus = null,
        ?string $newStatus = null
    ): void {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'model_type' => Appointment::class,
            'model_id' => $appointment->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'ip_address' => $request->ip(),
        ]);
    }
}
