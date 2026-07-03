<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesVisibleUsers;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    use ScopesVisibleUsers;

    public function index(Request $request): AnonymousResourceCollection
    {
        $visibleIds = $this->visibleUserIds($request->user());

        if ($request->filled('userId')) {
            abort_unless(in_array((int) $request->integer('userId'), $visibleIds, true), 403, 'No tienes acceso al historial de este miembro.');
            $visibleIds = [$request->integer('userId')];
        }

        $query = Exam::whereIn('user_id', $visibleIds)->with('patient');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('type')) {
            $query->where('exam_type', $request->string('type'));
        }

        return ExamResource::collection($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $patient = User::findOrFail($request->integer('user_id'));
        $this->authorize('create', [Exam::class, $patient]);

        $data = $request->validate([
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'name' => 'required|string|max:255',
            'exam_type' => 'required|in:laboratorio,imagen,especializado,otro',
            'lab_or_center' => 'nullable|string|max:255',
            'urgency' => 'nullable|in:rutina,urgente',
            'status' => 'nullable|in:sin_orden,con_orden,en_autorizacion,autorizado',
            'scheduled_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $exam = Exam::create([
            ...$data,
            'household_id' => $user->household_id,
            'user_id' => $patient->id,
            'registered_by' => $user->id,
            'urgency' => $data['urgency'] ?? 'rutina',
            'status' => $data['status'] ?? 'con_orden',
        ]);

        return (new ExamResource($exam->load('patient')))
            ->additional(['message' => 'Examen registrado correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): ExamResource
    {
        $exam = Exam::with('patient')->findOrFail($id);
        $this->authorize('view', $exam);

        return new ExamResource($exam);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('update', $exam);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'exam_type' => 'sometimes|in:laboratorio,imagen,especializado,otro',
            'lab_or_center' => 'nullable|string|max:255',
            'urgency' => 'nullable|in:rutina,urgente',
            'scheduled_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $exam->update($data);

        return (new ExamResource($exam->fresh('patient')))
            ->additional(['message' => 'Examen actualizado correctamente.'])
            ->response();
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('update', $exam);

        $target = $request->string('status')->toString();

        $data = $request->validate([
            'status' => 'required|in:en_autorizacion,autorizado,negado,agendado,muestra_tomada,resultado_pendiente,resultado_disponible,entregado_medico,cancelado',
            'authorization_number' => [Rule::requiredIf($target === 'autorizado'), 'nullable', 'string', 'max:100'],
            'authorization_date' => [Rule::requiredIf($target === 'autorizado'), 'nullable', 'date'],
            'denied_reason' => [Rule::requiredIf($target === 'negado'), 'nullable', 'string'],
            'scheduled_date' => [Rule::requiredIf($target === 'agendado'), 'nullable', 'date'],
            'performed_date' => [Rule::requiredIf($target === 'muestra_tomada'), 'nullable', 'date'],
            'result_date' => [Rule::requiredIf(in_array($target, ['resultado_pendiente', 'resultado_disponible'], true)), 'nullable', 'date'],
            'result_summary' => 'nullable|string',
            'delivered_to_doctor_date' => [Rule::requiredIf($target === 'entregado_medico'), 'nullable', 'date'],
            'cancelled_reason' => [Rule::requiredIf($target === 'cancelado'), 'nullable', 'string'],
        ]);

        $updates = ['status' => $data['status']];
        foreach ([
            'authorization_number', 'authorization_date', 'denied_reason', 'scheduled_date',
            'performed_date', 'result_date', 'result_summary', 'delivered_to_doctor_date', 'cancelled_reason',
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $updates[$field] = $data[$field];
            }
        }

        $exam->update($updates);

        return (new ExamResource($exam->fresh('patient')))
            ->additional(['message' => 'Estado del examen actualizado.'])
            ->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('delete', $exam);
        $exam->delete();

        return response()->json(['message' => 'Examen eliminado correctamente.']);
    }
}
