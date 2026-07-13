<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesVisibleUsers;
use App\Http\Controllers\Controller;
use App\Http\Resources\VitalSignResource;
use App\Models\Household;
use App\Models\Notification;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\VitalSignRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VitalSignController extends Controller
{
    use ScopesVisibleUsers;

    public function index(Request $request): AnonymousResourceCollection
    {
        $visibleIds = $this->visibleUserIds($request->user());

        if ($request->filled('userId')) {
            abort_unless(in_array((int) $request->integer('userId'), $visibleIds, true), 403, 'No tienes acceso al historial de este miembro.');
            $visibleIds = [$request->integer('userId')];
        }

        $query = VitalSign::whereIn('user_id', $visibleIds)->with(['patient', 'range']);

        if ($request->filled('from')) {
            $query->where('measurement_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('measurement_date', '<=', $request->date('to'));
        }

        $query->orderByDesc('measurement_date');

        // Opcional: para un paciente crónico con años de mediciones, algunas
        // vistas (ej. el gráfico de últimas 12 lecturas) solo necesitan las
        // más recientes — sin este parámetro el comportamiento es igual que
        // antes (trae todo, retrocompatible).
        if ($request->filled('limit')) {
            $query->limit($request->integer('limit'));
        }

        return VitalSignResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $patient = User::findOrFail($request->integer('user_id'));
        $this->authorize('create', [VitalSign::class, $patient]);

        $data = $request->validate([
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'measurement_date' => 'nullable|date',
            'systolic_pressure' => 'nullable|integer|min:0|max:300',
            'diastolic_pressure' => 'nullable|integer|min:0|max:300',
            'heart_rate' => 'nullable|integer|min:0|max:300',
            'blood_glucose' => 'nullable|numeric|min:0|max:999',
            'weight' => 'nullable|numeric|min:0|max:500',
            'height' => 'nullable|numeric|min:0|max:300',
            'temperature' => 'nullable|numeric|min:0|max:50',
            'oxygen_saturation' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $vitalSign = VitalSign::create([
            ...$data,
            'user_id' => $patient->id,
            'registered_by' => $user->id,
            'measurement_date' => $data['measurement_date'] ?? now(),
        ]);

        $vitalSign->load('patient');

        $this->notifyIfOutOfRange($vitalSign);

        // notifyIfOutOfRange() puede haber creado el rango por defecto recién —
        // recargar para que la respuesta refleje el out_of_range correcto.
        $vitalSign->load('range');

        return (new VitalSignResource($vitalSign))
            ->additional(['message' => 'Signos vitales registrados correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vitalSign = VitalSign::findOrFail($id);
        $this->authorize('update', $vitalSign);

        $data = $request->validate([
            'measurement_date' => 'sometimes|date',
            'systolic_pressure' => 'nullable|integer|min:0|max:300',
            'diastolic_pressure' => 'nullable|integer|min:0|max:300',
            'heart_rate' => 'nullable|integer|min:0|max:300',
            'blood_glucose' => 'nullable|numeric|min:0|max:999',
            'weight' => 'nullable|numeric|min:0|max:500',
            'height' => 'nullable|numeric|min:0|max:300',
            'temperature' => 'nullable|numeric|min:0|max:50',
            'oxygen_saturation' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $vitalSign->update($data);

        return (new VitalSignResource($vitalSign->fresh(['patient', 'range'])))
            ->additional(['message' => 'Registro actualizado correctamente.'])
            ->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $vitalSign = VitalSign::findOrFail($id);
        $this->authorize('delete', $vitalSign);
        $vitalSign->delete();

        return response()->json(['message' => 'Registro eliminado correctamente.']);
    }

    /** Notifica al paciente y al owner del hogar si algún valor está fuera de rango normal. */
    private function notifyIfOutOfRange(VitalSign $vitalSign): void
    {
        $range = $vitalSign->range ?? VitalSignRange::create(['user_id' => $vitalSign->user_id]);

        $outOfRange = [];
        if ($vitalSign->systolic_pressure !== null && $range->isSystolicOutOfRange($vitalSign->systolic_pressure)) {
            $outOfRange[] = 'presión sistólica';
        }
        if ($vitalSign->diastolic_pressure !== null && $range->isDiastolicOutOfRange($vitalSign->diastolic_pressure)) {
            $outOfRange[] = 'presión diastólica';
        }
        if ($vitalSign->blood_glucose !== null && $range->isGlucoseOutOfRange((float) $vitalSign->blood_glucose)) {
            $outOfRange[] = 'glucosa';
        }
        if ($vitalSign->oxygen_saturation !== null && $range->isOxygenOutOfRange($vitalSign->oxygen_saturation)) {
            $outOfRange[] = 'saturación de oxígeno';
        }

        if (empty($outOfRange)) {
            return;
        }

        $household = Household::find($vitalSign->patient->household_id);
        $recipients = collect([$vitalSign->user_id, $household?->owner_id])->filter()->unique();
        $lista = implode(', ', $outOfRange);

        foreach ($recipients as $recipientId) {
            Notification::create([
                'user_id' => $recipientId,
                'type' => 'vital_sign.out_of_range',
                'title' => '🔴 Signo vital fuera de rango',
                'body' => "{$vitalSign->patient->name} registró valores fuera de rango: {$lista}.",
                'data' => ['vital_sign_id' => $vitalSign->id],
                'priority' => 'danger',
            ]);
        }
    }
}
