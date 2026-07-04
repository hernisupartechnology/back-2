<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Envía notificaciones push (Web Push API) a los dispositivos suscritos de un usuario.
 * Si las llaves VAPID no están configuradas en .env (ver README, sección "Notificaciones push"),
 * se omite el envío silenciosamente — la app sigue funcionando solo con notificaciones in-app.
 */
class PushNotificationService
{
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * Envía el payload a todas las suscripciones activas del usuario.
     * Desactiva automáticamente las suscripciones que el navegador ya no reconoce (410/404).
     */
    public function sendToUser(User $user, array $payload): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $subscriptions = $user->pushSubscriptions()->where('is_active', true)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh,
                    'authToken' => $subscription->auth,
                ]),
                json_encode($payload)
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->update(['is_active' => false]);
            } else {
                Log::warning('Web push falló', ['endpoint' => $report->getEndpoint(), 'reason' => $report->getReason()]);
            }
        }
    }
}
