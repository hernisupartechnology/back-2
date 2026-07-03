<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesVisibleUsers;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Exam;
use App\Models\Medication;
use App\Models\Referral;
use App\Models\User;
use App\Services\MedicationIntakeService;
use App\Services\TrafficLightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel principal — resumen personal, semáforo de alertas
 * del hogar y actividad reciente.
 */
class DashboardController extends Controller
{
    use ScopesVisibleUsers;

    public function __construct(
        private readonly TrafficLightService $trafficLight,
        private readonly MedicationIntakeService $intakeService,
    ) {}

    /**
     * Resumen personal: próxima cita, medicamentos activos, exámenes con
     * resultado, remisiones autorizadas sin agendar y tomas de hoy.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $visibleIds = $this->visibleUserIds($user);

        $nextAppointment = Appointment::whereIn('user_id', $visibleIds)
            ->whereIn('status', ['programada', 'confirmada'])
            ->whereNotNull('appointment_date')
            ->where('appointment_date', '>=', now())
            ->with(['doctor', 'patient'])
            ->orderBy('appointment_date')
            ->first();

        $activeMedications = Medication::whereIn('user_id', $visibleIds)
            ->where('status', 'en_uso')
            ->count();

        $examsWithResults = Exam::whereIn('user_id', $visibleIds)
            ->where('status', 'resultado_disponible')
            ->count();

        $pendingReferrals = Referral::whereIn('user_id', $visibleIds)
            ->where('status', 'autorizada')
            ->count();

        $todayIntakes = $this->intakeService->todayIntakesFor($visibleIds);

        return response()->json([
            'next_appointment' => $nextAppointment ? new AppointmentResource($nextAppointment) : null,
            'active_medications' => $activeMedications,
            'exams_with_results' => $examsWithResults,
            'pending_referrals' => $pendingReferrals,
            'today_intakes' => $todayIntakes,
            'today_intakes_completed' => $todayIntakes->isNotEmpty()
                && $todayIntakes->every(fn ($i) => in_array($i['display_status'], ['tomado_a_tiempo', 'tomado_tarde'], true)),
        ]);
    }

    /**
     * Semáforo completo del hogar: citas, necesidades y renovaciones de
     * medicamentos que requieren atención, agrupadas por nivel de urgencia.
     */
    public function alerts(Request $request): JsonResponse
    {
        $user = $request->user();
        $visibleIds = $this->visibleUserIds($user);
        $members = User::whereIn('id', $visibleIds)->get(['id', 'name', 'avatar'])->keyBy('id');

        $alerts = collect();

        $appointments = Appointment::whereIn('user_id', $visibleIds)
            ->whereNotIn('status', ['realizada', 'cancelada', 'no_asistio'])
            ->get();

        foreach ($appointments as $appointment) {
            $tl = $this->trafficLight->forAppointment($appointment);
            if (! in_array($tl['level'], ['red', 'yellow'], true)) {
                continue;
            }

            $alerts->push([
                'type' => $appointment->is_need ? 'appointment.need' : 'appointment.scheduled',
                'level' => $tl['level'],
                'title' => $appointment->is_need
                    ? "Necesidad sin agendar — {$appointment->specialty}"
                    : "Cita de {$appointment->specialty}",
                'description' => $tl['label'],
                'member' => $members->get($appointment->user_id),
                'related_id' => $appointment->id,
                'related_type' => 'appointment',
            ]);
        }

        $medications = Medication::whereIn('user_id', $visibleIds)
            ->where('is_recurring', true)
            ->whereNotIn('status', ['completado', 'suspendido'])
            ->get();

        foreach ($medications as $medication) {
            $tl = $this->trafficLight->forMedicationRenewal($medication);
            if (! $tl || ! in_array($tl['level'], ['red', 'yellow'], true)) {
                continue;
            }

            $alerts->push([
                'type' => 'medication.renewal',
                'level' => $tl['level'],
                'title' => "Renovación de {$medication->name}",
                'description' => $tl['label'],
                'member' => $members->get($medication->user_id),
                'related_id' => $medication->id,
                'related_type' => 'medication',
            ]);
        }

        $referrals = Referral::whereIn('user_id', $visibleIds)
            ->where('status', 'autorizada')
            ->get();

        foreach ($referrals as $referral) {
            $tl = $this->trafficLight->forReferral($referral);
            if (! $tl || ! in_array($tl['level'], ['red', 'yellow'], true)) {
                continue;
            }

            $alerts->push([
                'type' => 'referral.expiring',
                'level' => $tl['level'],
                'title' => "Remisión a {$referral->specialty} sin agendar",
                'description' => $tl['label'],
                'member' => $members->get($referral->user_id),
                'related_id' => $referral->id,
                'related_type' => 'referral',
            ]);
        }

        $examsReady = Exam::whereIn('user_id', $visibleIds)
            ->where('status', 'resultado_disponible')
            ->get();

        foreach ($examsReady as $exam) {
            $alerts->push([
                'type' => 'exam.result_available',
                'level' => 'blue',
                'title' => "Resultado disponible — {$exam->name}",
                'description' => 'Listo para entregar al médico',
                'member' => $members->get($exam->user_id),
                'related_id' => $exam->id,
                'related_type' => 'exam',
            ]);
        }

        $order = ['red' => 0, 'yellow' => 1, 'blue' => 2, 'green' => 3, 'grey' => 4];
        $sorted = $alerts->sortBy(fn ($a) => $order[$a['level']] ?? 9)->values();

        return response()->json([
            'alerts' => $sorted,
            'summary' => [
                'red' => $sorted->where('level', 'red')->count(),
                'yellow' => $sorted->where('level', 'yellow')->count(),
                'blue' => $sorted->where('level', 'blue')->count(),
            ],
        ]);
    }

    /**
     * Timeline de los últimos eventos del hogar (creación y cambios de estado).
     */
    public function activity(Request $request): JsonResponse
    {
        $user = $request->user();
        $visibleIds = $this->visibleUserIds($user);

        $activity = ActivityLog::whereIn('user_id', $visibleIds)
            ->with('user:id,name,avatar')
            ->latest('created_at')
            ->limit(10)
            ->get();

        return response()->json(['activity' => $activity]);
    }
}
