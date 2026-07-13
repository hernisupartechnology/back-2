<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HouseholdResource;
use App\Http\Resources\UserResource;
use App\Mail\HouseholdInvitationMail;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Controlador de hogares — gestión de miembros, invitaciones y roles.
 */
class HouseholdController extends Controller
{
    /**
     * Crear un nuevo hogar.
     * Solo puede hacerlo un usuario que aún no pertenece a ningún hogar.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $usuario = $request->user();

        if ($usuario->household_id) {
            return response()->json([
                'message' => 'Ya perteneces a un hogar. Para crear uno nuevo, primero debes salir del hogar actual.',
            ], 422);
        }

        DB::transaction(function () use ($request, $usuario) {
            $hogar = Household::create([
                'name' => $request->name,
                'owner_id' => $usuario->id,
            ]);

            $usuario->update([
                'household_id' => $hogar->id,
                'role' => 'owner',
            ]);
        });

        return response()->json([
            'message' => '¡Hogar creado exitosamente! Ahora puedes invitar a tu familia.',
            'household' => new HouseholdResource($request->user()->fresh()->household),
        ], 201);
    }

    /**
     * Obtener el hogar actual del usuario autenticado.
     */
    public function current(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if (! $usuario->household_id) {
            return response()->json([
                'message' => 'Aún no perteneces a ningún hogar.',
                'household' => null,
            ]);
        }

        $hogar = Household::with([
            'owner',
            'members' => fn ($q) => $q->with(['allergies', 'chronicConditions']),
        ])->findOrFail($usuario->household_id);

        return response()->json(['household' => new HouseholdResource($hogar)]);
    }

    /**
     * Actualizar el nombre o avatar del hogar.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $hogar = Household::findOrFail($id);

        $this->authorize('update', $hogar);

        $request->validate(['name' => 'sometimes|string|max:100']);

        $hogar->update($request->only('name'));

        return response()->json([
            'message' => 'Hogar actualizado correctamente.',
            'household' => new HouseholdResource($hogar->fresh()),
        ]);
    }

    /**
     * Listar los miembros del hogar.
     */
    public function members(Request $request, int $id): JsonResponse
    {
        $hogar = Household::findOrFail($id);
        $this->authorize('view', $hogar);

        $miembros = $hogar->members()
            ->with(['allergies', 'chronicConditions'])
            ->get();

        return response()->json([
            'members' => UserResource::collection($miembros),
        ]);
    }

    /**
     * Crear directamente un "perfil gestionado" — un familiar sin correo ni
     * contraseña propios (niños, adultos mayores, cualquiera que no vaya a
     * usar la app por su cuenta). El owner administra toda su información
     * médica; este perfil nunca podrá iniciar sesión por sí mismo.
     */
    public function createManagedMember(Request $request, int $id): JsonResponse
    {
        $hogar = Household::findOrFail($id);
        $this->authorize('manage', $hogar);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'birthdate' => 'nullable|date|before:today',
            'gender' => 'nullable|in:masculino,femenino,otro',
            'blood_type' => 'nullable|in:A+,A-,B+,B-,O+,O-,AB+,AB-',
            'eps' => 'nullable|string|max:255',
            'ips_preferida' => 'nullable|string|max:255',
            'numero_afiliado' => 'nullable|string|max:255',
            'is_minor' => 'sometimes|boolean',
            'supervised_by' => ['nullable', 'integer', Rule::exists('users', 'id')->where('household_id', $id)],
        ]);

        $miembro = User::create([
            ...$data,
            'household_id' => $id,
            'role' => 'viewer',
            'is_managed' => true,
            'email' => null,
            'password' => null,
        ]);

        return response()->json([
            'message' => "{$miembro->name} fue agregado al hogar.",
            'member' => new UserResource($miembro),
        ], 201);
    }

    /**
     * Editar el perfil de un miembro gestionado — como no puede iniciar
     * sesión, no puede usar `ProfileController::update()` (solo opera sobre
     * el usuario autenticado); el owner/supervisor lo edita por él.
     */
    public function updateManagedMember(Request $request, int $id, int $userId): JsonResponse
    {
        $miembro = User::where('id', $userId)->where('household_id', $id)->where('is_managed', true)->firstOrFail();

        abort_unless($request->user()->canManage($miembro), 403, 'No tienes acceso a este perfil.');

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:20',
            'birthdate' => 'nullable|date|before:today',
            'gender' => 'nullable|in:masculino,femenino,otro',
            'blood_type' => 'nullable|in:A+,A-,B+,B-,O+,O-,AB+,AB-',
            'eps' => 'nullable|string|max:255',
            'ips_preferida' => 'nullable|string|max:255',
            'numero_afiliado' => 'nullable|string|max:255',
            'is_minor' => 'sometimes|boolean',
            'supervised_by' => ['nullable', 'integer', Rule::exists('users', 'id')->where('household_id', $id)],
        ]);

        $miembro->update($data);

        return response()->json([
            'message' => "Perfil de {$miembro->name} actualizado correctamente.",
            'member' => new UserResource($miembro->fresh()),
        ]);
    }

    /**
     * Invitar a un miembro por correo electrónico.
     */
    public function invite(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'role_assigned' => 'required|in:member,viewer',
        ]);

        $usuario = $request->user();
        $this->authorize('manage', $usuario->household);

        $invitacion = HouseholdInvitation::create([
            'household_id' => $usuario->household_id,
            'email' => $request->email,
            'role_assigned' => $request->role_assigned,
            'invited_by' => $usuario->id,
        ]);

        Mail::to($invitacion->email)->send(new HouseholdInvitationMail($invitacion->load(['household', 'invitedBy'])));

        return response()->json([
            'message' => "Invitación enviada a {$request->email}.",
            'invitation' => $invitacion,
        ], 201);
    }

    /**
     * Unirse a un hogar con el token de 8 caracteres.
     */
    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:8',
        ]);

        $usuario = $request->user();

        if ($usuario->household_id) {
            return response()->json([
                'message' => 'Ya perteneces a un hogar.',
            ], 422);
        }

        $invitacion = HouseholdInvitation::where('token', strtoupper($request->token))
            ->where('status', 'pending')
            ->first();

        if (! $invitacion || $invitacion->isExpired()) {
            return response()->json([
                'message' => 'El código de invitación no es válido o ya expiró.',
            ], 422);
        }

        DB::transaction(function () use ($usuario, $invitacion) {
            $usuario->update([
                'household_id' => $invitacion->household_id,
                'role' => $invitacion->role_assigned,
            ]);

            $invitacion->update(['status' => 'accepted']);
        });

        return response()->json([
            'message' => '¡Te uniste al hogar exitosamente!',
            'household' => new HouseholdResource($usuario->fresh()->household),
        ]);
    }

    /**
     * Cambiar el rol de un miembro.
     */
    public function updateRole(Request $request, int $id, int $userId): JsonResponse
    {
        $request->validate(['role' => 'required|in:member,viewer']);

        $usuario = $request->user();
        $this->authorize('manage', $usuario->household);

        $miembro = User::where('id', $userId)
            ->where('household_id', $id)
            ->firstOrFail();

        // El owner no puede cambiar su propio rol
        if ($miembro->id === $usuario->id) {
            return response()->json(['message' => 'No puedes cambiar tu propio rol.'], 422);
        }

        $miembro->update(['role' => $request->role]);

        return response()->json([
            'message' => "Rol de {$miembro->name} actualizado a {$request->role}.",
            'member' => new UserResource($miembro),
        ]);
    }

    /**
     * Asignar supervisor a un miembro (menores de edad).
     */
    public function updateSupervisor(Request $request, int $id, int $userId): JsonResponse
    {
        $request->validate(['supervised_by' => 'nullable|exists:users,id']);

        $miembro = User::where('id', $userId)
            ->where('household_id', $id)
            ->firstOrFail();

        $miembro->update(['supervised_by' => $request->supervised_by]);

        return response()->json([
            'message' => 'Supervisor actualizado correctamente.',
            'member' => new UserResource($miembro),
        ]);
    }

    /**
     * Eliminar un miembro del hogar.
     */
    public function removeMember(Request $request, int $id, int $userId): JsonResponse
    {
        $usuario = $request->user();
        $this->authorize('manage', $usuario->household);

        $miembro = User::where('id', $userId)
            ->where('household_id', $id)
            ->firstOrFail();

        if ($miembro->id === $usuario->id) {
            return response()->json(['message' => 'No puedes eliminarte a ti mismo del hogar.'], 422);
        }

        $miembro->update(['household_id' => null, 'role' => 'member']);

        return response()->json(['message' => "{$miembro->name} fue eliminado del hogar."]);
    }

    /**
     * Transferir la propiedad del hogar a otro miembro.
     */
    public function transferOwnership(Request $request, int $id): JsonResponse
    {
        $request->validate(['new_owner_id' => 'required|exists:users,id']);

        $usuario = $request->user();
        $hogar = Household::findOrFail($id);

        if ($hogar->owner_id !== $usuario->id) {
            return response()->json(['message' => 'Solo el propietario puede transferir el hogar.'], 403);
        }

        $nuevoOwner = User::where('id', $request->new_owner_id)
            ->where('household_id', $id)
            ->firstOrFail();

        DB::transaction(function () use ($hogar, $usuario, $nuevoOwner) {
            $hogar->update(['owner_id' => $nuevoOwner->id]);
            $usuario->update(['role' => 'member']);
            $nuevoOwner->update(['role' => 'owner']);
        });

        return response()->json([
            'message' => "La propiedad del hogar fue transferida a {$nuevoOwner->name}.",
        ]);
    }
}
