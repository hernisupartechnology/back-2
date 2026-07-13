<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices que faltaban para consultas reales de la app que crecen con el
 * tiempo/número de usuarios (ver auditoría de escalabilidad):
 * - notifications.created_at: CheckHealthAlertsJob precarga "lo notificado
 *   hoy" con un solo whereBetween(created_at) sin user_id — sin este índice
 *   es un full scan de toda la tabla en cada corrida diaria.
 * - activity_log(user_id, created_at): Dashboard::activity() ordena por
 *   created_at dentro de un whereIn(user_id) — el índice existente
 *   (user_id, action) no sirve para ese orderBy.
 * - medical_leaves(user_id, start_date): mismo patrón que appointments/
 *   medications, filtro por miembro + rango de fechas + orderBy.
 * - medical_documents.document_date: orderByDesc sin índice de soporte.
 * - vaccinations(user_id, application_date): filtro por miembro + orderBy;
 *   ver también el cambio de whereYear() a whereBetween() en
 *   VaccinationController — whereYear() envuelve la columna y anula
 *   cualquier índice, sin importar cuál se agregue acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('medical_leaves', function (Blueprint $table) {
            $table->index(['user_id', 'start_date']);
        });

        Schema::table('medical_documents', function (Blueprint $table) {
            $table->index('document_date');
        });

        Schema::table('vaccinations', function (Blueprint $table) {
            $table->index(['user_id', 'application_date']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('medical_leaves', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'start_date']);
        });

        Schema::table('medical_documents', function (Blueprint $table) {
            $table->dropIndex(['document_date']);
        });

        Schema::table('vaccinations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'application_date']);
        });
    }
};
