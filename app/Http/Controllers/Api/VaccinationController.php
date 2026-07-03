<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesVisibleUsers;
use App\Http\Controllers\Controller;
use App\Http\Resources\VaccinationResource;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VaccinationController extends Controller
{
    use ScopesVisibleUsers;

    public function index(Request $request): AnonymousResourceCollection
    {
        $visibleIds = $this->visibleUserIds($request->user());

        if ($request->filled('userId')) {
            abort_unless(in_array((int) $request->integer('userId'), $visibleIds, true), 403, 'No tienes acceso al historial de este miembro.');
            $visibleIds = [$request->integer('userId')];
        }

        $query = Vaccination::whereIn('user_id', $visibleIds)->with('patient');

        if ($request->filled('name')) {
            $query->where('vaccine_name', 'like', '%'.$request->string('name').'%');
        }
        if ($request->filled('year')) {
            $query->whereYear('application_date', $request->integer('year'));
        }

        return VaccinationResource::collection($query->orderByDesc('application_date')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $patient = User::findOrFail($request->integer('user_id'));
        $this->authorize('create', [Vaccination::class, $patient]);

        $data = $request->validate([
            'vaccine_name' => 'required|string|max:255',
            'dose_number' => 'nullable|string|max:100',
            'application_date' => 'required|date',
            'applied_by' => 'nullable|string|max:255',
            'lot_number' => 'nullable|string|max:100',
            'ips_or_center' => 'nullable|string|max:255',
            'next_dose_date' => 'nullable|date|after:application_date',
            'notes' => 'nullable|string',
        ]);

        $vaccination = Vaccination::create([
            ...$data,
            'user_id' => $patient->id,
            'registered_by' => $user->id,
        ]);

        return (new VaccinationResource($vaccination->load('patient')))
            ->additional(['message' => '¡Vacuna registrada correctamente!'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vaccination = Vaccination::findOrFail($id);
        $this->authorize('update', $vaccination);

        $data = $request->validate([
            'vaccine_name' => 'sometimes|string|max:255',
            'dose_number' => 'nullable|string|max:100',
            'application_date' => 'sometimes|date',
            'applied_by' => 'nullable|string|max:255',
            'lot_number' => 'nullable|string|max:100',
            'ips_or_center' => 'nullable|string|max:255',
            'next_dose_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $vaccination->update($data);

        return (new VaccinationResource($vaccination->fresh('patient')))
            ->additional(['message' => 'Vacuna actualizada correctamente.'])
            ->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $vaccination = Vaccination::findOrFail($id);
        $this->authorize('delete', $vaccination);
        $vaccination->delete();

        return response()->json(['message' => 'Vacuna eliminada correctamente.']);
    }
}
