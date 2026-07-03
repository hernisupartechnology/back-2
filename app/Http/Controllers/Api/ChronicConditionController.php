<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChronicCondition;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CRUD de condiciones crónicas del perfil médico — chips azules en el historial. */
class ChronicConditionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $patient = User::findOrFail($request->integer('userId'));
        abort_unless($request->user()->canManage($patient) || $request->user()->id === $patient->id, 403);

        return response()->json(['chronic_conditions' => $patient->chronicConditions()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $patient = User::findOrFail($request->integer('user_id'));
        abort_unless($request->user()->household_id === $patient->household_id && $request->user()->canManage($patient), 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'diagnosed_date' => 'nullable|date',
            'treating_doctor_id' => 'nullable|integer|exists:doctors,id',
            'notes' => 'nullable|string',
        ]);

        $condition = ChronicCondition::create([...$data, 'user_id' => $patient->id, 'is_active' => true]);

        return response()->json(['message' => 'Condición crónica registrada correctamente.', 'chronic_condition' => $condition], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $condition = ChronicCondition::findOrFail($id);
        abort_unless($request->user()->household_id === $condition->patient->household_id && $request->user()->canManage($condition->patient), 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'diagnosed_date' => 'nullable|date',
            'treating_doctor_id' => 'nullable|integer|exists:doctors,id',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $condition->update($data);

        return response()->json(['message' => 'Condición crónica actualizada correctamente.', 'chronic_condition' => $condition->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $condition = ChronicCondition::findOrFail($id);
        abort_unless($request->user()->household_id === $condition->patient->household_id && $request->user()->canManage($condition->patient), 403);

        $condition->delete();

        return response()->json(['message' => 'Condición crónica eliminada correctamente.']);
    }
}
