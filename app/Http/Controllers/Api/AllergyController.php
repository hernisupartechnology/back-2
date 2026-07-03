<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Allergy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CRUD de alergias del perfil médico — se muestran como chips rojos en el historial. */
class AllergyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $patient = User::findOrFail($request->integer('userId'));
        abort_unless($request->user()->canManage($patient) || $request->user()->id === $patient->id, 403);

        return response()->json(['allergies' => $patient->allergies()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $patient = User::findOrFail($request->integer('user_id'));
        abort_unless($request->user()->household_id === $patient->household_id && $request->user()->canManage($patient), 403);

        $data = $request->validate([
            'type' => 'required|in:medicamento,alimento,ambiental,otro',
            'name' => 'required|string|max:255',
            'reaction' => 'nullable|string',
            'severity' => 'required|in:leve,moderada,grave',
            'diagnosed_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $allergy = Allergy::create([...$data, 'user_id' => $patient->id, 'is_active' => true]);

        return response()->json(['message' => 'Alergia registrada correctamente.', 'allergy' => $allergy], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $allergy = Allergy::findOrFail($id);
        abort_unless($request->user()->household_id === $allergy->patient->household_id && $request->user()->canManage($allergy->patient), 403);

        $data = $request->validate([
            'type' => 'sometimes|in:medicamento,alimento,ambiental,otro',
            'name' => 'sometimes|string|max:255',
            'reaction' => 'nullable|string',
            'severity' => 'sometimes|in:leve,moderada,grave',
            'diagnosed_date' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $allergy->update($data);

        return response()->json(['message' => 'Alergia actualizada correctamente.', 'allergy' => $allergy->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $allergy = Allergy::findOrFail($id);
        abort_unless($request->user()->household_id === $allergy->patient->household_id && $request->user()->canManage($allergy->patient), 403);

        $allergy->delete();

        return response()->json(['message' => 'Alergia eliminada correctamente.']);
    }
}
