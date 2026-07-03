<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DoctorResource;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Catálogo de médicos del hogar — compartido entre todos los miembros.
 */
class DoctorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $doctors = Doctor::where('household_id', $request->user()->household_id)
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return DoctorResource::collection($doctors);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Doctor::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'specialty' => 'required|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'ips' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $doctor = Doctor::create([...$data, 'household_id' => $request->user()->household_id]);

        return (new DoctorResource($doctor))
            ->additional(['message' => 'Médico agregado al catálogo correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): DoctorResource
    {
        $doctor = Doctor::findOrFail($id);
        $this->authorize('view', $doctor);

        return new DoctorResource($doctor->load(['appointments' => fn ($q) => $q->latest('appointment_date')->limit(10)]));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $doctor = Doctor::findOrFail($id);
        $this->authorize('update', $doctor);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'specialty' => 'sometimes|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'ips' => 'sometimes|string|max:255',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $doctor->update($data);

        return (new DoctorResource($doctor->fresh()))
            ->additional(['message' => 'Médico actualizado correctamente.'])
            ->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $doctor = Doctor::findOrFail($id);
        $this->authorize('delete', $doctor);

        // No se elimina físicamente (hay citas/incapacidades referenciándolo) — se desactiva.
        $doctor->update(['is_active' => false]);

        return response()->json(['message' => 'Médico desactivado del catálogo.']);
    }
}
