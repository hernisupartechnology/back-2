<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Suscripciones Web Push del navegador — el frontend llama a store() al
 * activar recordatorios de toma por primera vez (PushManager.subscribe()).
 */
class PushSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'subscriptions' => $request->user()->pushSubscriptions()->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => 'required|string',
            'p256dh' => 'required|string',
            'auth' => 'required|string',
            'device_label' => 'nullable|string|max:255',
        ]);

        $subscription = PushSubscription::updateOrCreate(
            ['user_id' => $request->user()->id, 'endpoint' => $data['endpoint']],
            [
                'p256dh' => $data['p256dh'],
                'auth' => $data['auth'],
                'device_label' => $data['device_label'] ?? $this->guessDeviceLabel($request),
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Notificaciones push activadas en este dispositivo.',
            'subscription' => $subscription,
        ], 201);
    }

    /** Desactiva la suscripción de este navegador (el frontend manda el endpoint a borrar). */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => 'required|string']);

        $updated = PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $data['endpoint'])
            ->update(['is_active' => false]);

        abort_if($updated === 0, 404, 'No se encontró esa suscripción.');

        return response()->json(['message' => 'Notificaciones push desactivadas en este dispositivo.']);
    }

    private function guessDeviceLabel(Request $request): string
    {
        $agent = $request->userAgent() ?? '';

        return match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Windows') => 'Windows',
            default => 'Dispositivo desconocido',
        };
    }
}
