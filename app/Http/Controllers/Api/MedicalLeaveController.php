<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesVisibleUsers;
use App\Http\Controllers\Controller;
use App\Http\Resources\MedicalLeaveResource;
use App\Models\MedicalLeave;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalLeaveController extends Controller
{
    use ScopesVisibleUsers;

    private const PER_PAGE = 20;

    public function index(Request $request): JsonResponse
    {
        $visibleIds = $this->visibleUserIds($request->user());

        if ($request->filled('userId')) {
            abort_unless(in_array((int) $request->integer('userId'), $visibleIds, true), 403, 'No tienes acceso al historial de este miembro.');
            $visibleIds = [$request->integer('userId')];
        }

        $query = MedicalLeave::whereIn('user_id', $visibleIds)->with(['patient', 'issuingDoctor']);

        if ($request->filled('from')) {
            $query->where('start_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('end_date', '<=', $request->date('to'));
        }

        // Forma plana (data/current_page/last_page/...) para ser consistente con
        // NotificationController — el único otro endpoint paginado de la app.
        $leaves = $query->orderByDesc('start_date')->paginate(min(50, $request->integer('per_page') ?: self::PER_PAGE));

        return response()->json([
            'data' => MedicalLeaveResource::collection($leaves->items()),
            'current_page' => $leaves->currentPage(),
            'last_page' => $leaves->lastPage(),
            'per_page' => $leaves->perPage(),
            'total' => $leaves->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $patient = User::findOrFail($request->integer('user_id'));
        $this->authorize('create', [MedicalLeave::class, $patient]);

        $data = $request->validate([
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'issuing_doctor_id' => 'nullable|integer|exists:doctors,id',
            'issuing_doctor_name_free' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'diagnosis' => 'nullable|string',
            'diagnosis_code' => 'nullable|string|max:10',
            'leave_type' => 'required|in:enfermedad_general,accidente_trabajo,licencia_maternidad,licencia_paternidad,otro',
            'ips_issued' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $leave = MedicalLeave::create([
            ...$data,
            'household_id' => $user->household_id,
            'user_id' => $patient->id,
            'registered_by' => $user->id,
            'total_days' => 0, // el Observer lo recalcula al guardar
        ]);

        return (new MedicalLeaveResource($leave->load(['patient', 'issuingDoctor'])))
            ->additional(['message' => 'Incapacidad registrada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $leave = MedicalLeave::findOrFail($id);
        $this->authorize('update', $leave);

        $data = $request->validate([
            'issuing_doctor_id' => 'nullable|integer|exists:doctors,id',
            'issuing_doctor_name_free' => 'nullable|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'diagnosis' => 'nullable|string',
            'diagnosis_code' => 'nullable|string|max:10',
            'leave_type' => 'sometimes|in:enfermedad_general,accidente_trabajo,licencia_maternidad,licencia_paternidad,otro',
            'ips_issued' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $leave->update($data);

        return (new MedicalLeaveResource($leave->fresh(['patient', 'issuingDoctor'])))
            ->additional(['message' => 'Incapacidad actualizada correctamente.'])
            ->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $leave = MedicalLeave::findOrFail($id);
        $this->authorize('delete', $leave);
        $leave->delete();

        return response()->json(['message' => 'Incapacidad eliminada correctamente.']);
    }
}
