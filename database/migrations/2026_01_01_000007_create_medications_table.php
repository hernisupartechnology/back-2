<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de medicamentos — flujo completo desde sin_orden hasta completado.
 * Soporta medicamentos crónicos/recurrentes con alertas de renovación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->constrained('users');

            $table->string('name');
            $table->string('active_ingredient')->nullable(); // principio activo
            $table->string('presentation')->nullable(); // tabletas, jarabe, etc.
            $table->string('dosage'); // ej: "500mg", "10ml"
            $table->string('frequency'); // ej: "cada 8 horas"
            $table->integer('duration_days')->nullable();
            $table->integer('quantity')->nullable(); // cantidad a dispensar
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable(); // calculado por Observer: start_date + duration_days

            // Recurrencia / medicamento crónico
            $table->boolean('is_recurring')->default(false);
            $table->integer('recurrence_days')->nullable(); // cada cuántos días se renueva
            $table->integer('alert_days_before')->default(10); // días antes para alerta

            $table->enum('status', [
                'sin_orden', 'con_orden', 'en_autorizacion', 'autorizado',
                'negado', 'reclamado', 'en_uso', 'completado', 'vencido', 'suspendido',
            ])->default('con_orden');

            $table->text('denied_reason')->nullable();
            $table->date('authorization_date')->nullable();
            $table->string('authorization_number')->nullable();
            $table->date('claimed_date')->nullable();
            $table->text('notes')->nullable();

            // Seguimiento de tomas
            $table->boolean('track_intake')->default(false);
            $table->decimal('intake_quantity_per_dose', 5, 2)->nullable();
            $table->integer('remaining_doses')->nullable(); // calculado por Observer
            $table->integer('low_stock_alert_doses')->default(5);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['household_id', 'user_id', 'status']);
            $table->index(['is_recurring', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
