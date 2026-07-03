<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripciones Web Push de los navegadores/dispositivos.
 * Permite enviar notificaciones push aunque la app esté cerrada (PWA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint'); // URL del servicio push del navegador
            $table->text('p256dh'); // clave pública del cliente
            $table->text('auth'); // secreto de autenticación
            $table->string('device_label')->nullable(); // "Celular de Hernis", "Chrome PC"
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Un usuario puede tener múltiples dispositivos pero el endpoint debe ser único por usuario
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
