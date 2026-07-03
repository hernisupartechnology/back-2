<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Controlador de autenticación — registro, login, logout, recuperación de contraseña.
 */
class AuthController extends Controller
{
    /**
     * Registrar un nuevo usuario.
     * Crea el usuario y devuelve el token de acceso.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $usuario = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $token = $usuario->createToken('uparvital-token')->plainTextToken;

        return response()->json([
            'message' => '¡Bienvenido a UparVital! Tu cuenta fue creada exitosamente.',
            'user' => new UserResource($usuario),
            'token' => $token,
        ], 201);
    }

    /**
     * Iniciar sesión — devuelve token de acceso.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Las credenciales no son correctas. Verifica tu correo y contraseña.',
            ], 401);
        }

        /** @var User $usuario */
        $usuario = Auth::user();
        $token = $usuario->createToken('uparvital-token')->plainTextToken;

        return response()->json([
            'message' => '¡Hola, '.$usuario->name.'! Sesión iniciada correctamente.',
            'user' => new UserResource($usuario->load(['household', 'vitalSignRange'])),
            'token' => $token,
        ]);
    }

    /**
     * Cerrar sesión — revocar el token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente. ¡Hasta pronto!',
        ]);
    }

    /**
     * Obtener el usuario autenticado actual.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource(
                $request->user()->load([
                    'household.members',
                    'allergies' => fn ($q) => $q->where('is_active', true),
                    'chronicConditions' => fn ($q) => $q->where('is_active', true),
                    'vitalSignRange',
                ])
            ),
        ]);
    }

    /**
     * Enviar enlace de recuperación de contraseña.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Te enviamos un enlace para restablecer tu contraseña. Revisa tu correo.',
            ]);
        }

        return response()->json([
            'message' => 'No encontramos ninguna cuenta con ese correo electrónico.',
        ], 422);
    }

    /**
     * Restablecer la contraseña con el token del correo.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])
                    ->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => '¡Tu contraseña fue restablecida exitosamente! Ya puedes iniciar sesión.',
            ]);
        }

        return response()->json([
            'message' => 'El enlace de restablecimiento no es válido o ya expiró.',
        ], 422);
    }
}
