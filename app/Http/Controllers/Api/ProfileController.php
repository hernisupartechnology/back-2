<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
