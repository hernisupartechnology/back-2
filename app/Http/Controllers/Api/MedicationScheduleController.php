<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medication;
use App\Models\MedicationSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de horarios de toma de un medicamento.
 * store() reemplaza el conjunto completo de horarios (patrón "editor de horarios").
 */
class MedicationScheduleController extends Controller
{
    public function index(int $id): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('view', $medication);

        return response()->json(['schedules' => $medication->schedules()->get()]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('update', $medication);

        $data = $request->validate([
            'schedules' => 'required|array|min:1',
            'schedules.*.time_of_day' => 'required|date_format:H:i',
            'schedules.*.label' => 'nullable|string|max:100',
            'schedules.*.days_of_week' => 'nullable|array',
            'schedules.*.days_of_week.*' => 'integer|min:1|max:7',
            'schedules.*.reminder_minutes_before' => 'nullable|integer|in:0,5,10,15,30',
        ]);

        $medication->schedules()->delete();

        foreach ($data['schedules'] as $schedule) {
            MedicationSchedule::create([
                'medication_id' => $medication->id,
                'user_id' => $medication->user_id,
                'time_of_day' => $schedule['time_of_day'],
                'label' => $schedule['label'] ?? null,
                'days_of_week' => $schedule['days_of_week'] ?? null,
                'reminder_minutes_before' => $schedule['reminder_minutes_before'] ?? 5,
            ]);
        }

        return response()->json([
            'message' => 'Horarios de toma actualizados correctamente.',
            'schedules' => $medication->schedules()->get(),
        ], 201);
    }

    public function destroy(int $id, int $scheduleId): JsonResponse
    {
        $medication = Medication::findOrFail($id);
        $this->authorize('update', $medication);

        $medication->schedules()->where('id', $scheduleId)->firstOrFail()->delete();

        return response()->json(['message' => 'Horario eliminado correctamente.']);
    }
}
