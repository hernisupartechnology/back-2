<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesVisibleUsers;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReferralResource;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ReferralController extends Controller
{
    use ScopesVisibleUsers;

    private const RELATIONS = ['patient', 'referringDoctor', 'referredDoctor'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $visibleIds = $this->visibleUserIds($request->user());

        if ($request->filled('userId')) {
            abort_unless(in_array((int) $request->integer('userId'), $visibleIds, true), 403, 'No tienes acceso al historial de este miembro.');
            $visibleIds = [$request->integer('userId')];
        }

        $query = Referral::whereIn('user_id', $visibleIds)->with(self::RELATIONS);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('specialty')) {
            $query->where('specialty', 'like', '%'.$request->string('specialty').'%');
        }

        return ReferralResource::collection($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $patient = User::findOrFail($request->integer('user_id'));
        $this->authorize('create', [Referral::class, $patient]);

        $data = $request->validate([
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'specialty' => 'required|string|max:255',
            'referring_doctor_id' => 'nullable|integer|exists:doctors,id',
            'reason' => 'required|string',
            'urgency' => 'nullable|in:rutina,prioritaria,urgente',
            'authorization_expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $referral = Referral::create([
            ...$data,
            'household_id' => $user->household_id,
            'user_id' => $patient->id,
            'registered_by' => $user->id,
            'urgency' => $data['urgency'] ?? 'rutina',
            'status' => 'emitida',
        ]);

        return (new ReferralResource($referral->load(self::RELATIONS)))
            ->additional(['message' => 'Remisión registrada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): ReferralResource
    {
        $referral = Referral::with(self::RELATIONS)->findOrFail($id);
        $this->authorize('view', $referral);

        return new ReferralResource($referral);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $referral = Referral::findOrFail($id);
        $this->authorize('update', $referral);

        $data = $request->validate([
            'specialty' => 'sometimes|string|max:255',
            'referring_doctor_id' => 'nullable|integer|exists:doctors,id',
            'referred_doctor_id' => 'nullable|integer|exists:doctors,id',
            'reason' => 'sometimes|string',
            'urgency' => 'nullable|in:rutina,prioritaria,urgente',
            'notes' => 'nullable|string',
        ]);

        $referral->update($data);

        return (new ReferralResource($referral->fresh(self::RELATIONS)))
            ->additional(['message' => 'Remisión actualizada correctamente.'])
            ->response();
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $referral = Referral::findOrFail($id);
        $this->authorize('update', $referral);

        $target = $request->string('status')->toString();

        $data = $request->validate([
            'status' => 'required|in:en_autorizacion,autorizada,negada,cita_agendada,completada',
            'authorization_number' => [Rule::requiredIf($target === 'autorizada'), 'nullable', 'string', 'max:100'],
            'authorization_date' => [Rule::requiredIf($target === 'autorizada'), 'nullable', 'date'],
            'authorization_expiry_date' => [Rule::requiredIf($target === 'autorizada'), 'nullable', 'date'],
            'denied_reason' => [Rule::requiredIf($target === 'negada'), 'nullable', 'string'],
            'scheduled_appointment_id' => [Rule::requiredIf($target === 'cita_agendada'), 'nullable', 'integer', 'exists:appointments,id'],
            'referred_doctor_id' => 'nullable|integer|exists:doctors,id',
        ]);

        $updates = ['status' => $data['status']];
        foreach ([
            'authorization_number', 'authorization_date', 'authorization_expiry_date',
            'denied_reason', 'scheduled_appointment_id', 'referred_doctor_id',
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $updates[$field] = $data[$field];
            }
        }

        $referral->update($updates);

        return (new ReferralResource($referral->fresh(self::RELATIONS)))
            ->additional(['message' => 'Estado de la remisión actualizado.'])
            ->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $referral = Referral::findOrFail($id);
        $this->authorize('delete', $referral);
        $referral->delete();

        return response()->json(['message' => 'Remisión eliminada correctamente.']);
    }
}
