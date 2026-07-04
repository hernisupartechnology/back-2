<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\VitalSignRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Controlador del perfil del usuario autenticado — datos personales y de salud.
 */
class ProfileController extends Controller
{
    /**
     * Actualizar el perfil médico y personal del usuario autenticado.
     */
    public function update(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:20',
            'birthdate' => 'nullable|date|before:today',
            'gender' => 'nullable|in:masculino,femenino,otro',
            'blood_type' => 'nullable|in:A+,A-,B+,B-,O+,O-,AB+,AB-',
            'eps' => 'nullable|string|max:255',
            'ips_preferida' => 'nullable|string|max:255',
            'numero_afiliado' => 'nullable|string|max:255',
            'track_vital_signs' => 'sometimes|boolean',
            'dark_mode' => 'sometimes|boolean',
            'email' => [
                'sometimes', 'email',
                Rule::unique('users', 'email')->ignore($usuario->id),
            ],
        ]);

        $usuario->update($validated);

        return response()->json([
            'message' => 'Tu perfil fue actualizado correctamente.',
            'user' => new UserResource($usuario->fresh(['household', 'allergies', 'chronicConditions', 'vitalSignRange'])),
        ]);
    }

    /**
     * Subir o reemplazar la foto de perfil del usuario autenticado.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $usuario = $request->user();

        if ($usuario->avatar) {
            Storage::disk('public')->delete($usuario->avatar);
        }

        $path = $request->file('avatar')->storeAs(
            'avatars',
            Str::uuid().'.'.$request->file('avatar')->extension(),
            'public'
        );

        $usuario->update(['avatar' => $path]);

        return response()->json([
            'message' => 'Foto de perfil actualizada correctamente.',
            'user' => new UserResource($usuario->fresh()),
        ]);
    }

    /**
     * Obtener o actualizar los rangos normales de signos vitales de un miembro
     * (spec §9: "personalizar rangos normales por miembro, para pacientes con
     * condiciones especiales"). Por defecto aplica al usuario autenticado.
     */
    public function vitalSignRange(Request $request): JsonResponse
    {
        $target = $this->resolveTargetForRange($request);

        $range = $target->vitalSignRange ?? VitalSignRange::create(['user_id' => $target->id]);

        return response()->json(['range' => $range]);
    }

    public function updateVitalSignRange(Request $request): JsonResponse
    {
        $target = $this->resolveTargetForRange($request);

        $data = $request->validate([
            'systolic_min' => 'sometimes|integer|min:50|max:200',
            'systolic_max' => 'sometimes|integer|min:50|max:250',
            'diastolic_min' => 'sometimes|integer|min:30|max:150',
            'diastolic_max' => 'sometimes|integer|min:30|max:180',
            'glucose_min' => 'sometimes|numeric|min:20|max:200',
            'glucose_max' => 'sometimes|numeric|min:20|max:400',
            'oxygen_min' => 'sometimes|integer|min:70|max:100',
        ]);

        $range = VitalSignRange::updateOrCreate(['user_id' => $target->id], $data);

        return response()->json([
            'message' => 'Rangos de signos vitales actualizados correctamente.',
            'range' => $range,
        ]);
    }

    private function resolveTargetForRange(Request $request): User
    {
        $userId = $request->integer('user_id') ?: $request->user()->id;
        $target = User::findOrFail($userId);

        abort_unless($request->user()->id === $target->id
            || ($request->user()->household_id === $target->household_id && $request->user()->canManage($target)),
            403, 'No tienes acceso a los rangos de este miembro.');

        return $target;
    }

    /**
     * Exporta el historial médico completo del usuario autenticado en JSON
     * (spec §9 Configuración → Exportación).
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'household', 'allergies', 'chronicConditions', 'vitalSignRange',
            'appointments.doctor', 'medications.schedules', 'medications.renewals',
            'exams', 'referrals', 'medicalLeaves', 'vaccinations', 'vitalSigns', 'medicalDocuments',
        ]);

        return response()->json([
            'exported_at' => now()->toISOString(),
            'user' => $user,
        ]);
    }

    /**
     * Elimina permanentemente la cuenta del usuario autenticado (zona peligrosa).
     * El owner de un hogar con otros miembros no puede eliminarse sin transferir
     * antes la propiedad — evita dejar el hogar sin dueño.
     */
    public function destroyAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate(['password' => 'required|string']);
        abort_unless(Hash::check($data['password'], $user->password), 422, 'La contraseña no es correcta.');

        if ($user->isOwner()) {
            $otherMembers = User::where('household_id', $user->household_id)->where('id', '!=', $user->id)->exists();
            abort_if($otherMembers, 422, 'Transfiere la propiedad del hogar a otro miembro antes de eliminar tu cuenta.');
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Tu cuenta fue eliminada permanentemente.']);
    }
}
