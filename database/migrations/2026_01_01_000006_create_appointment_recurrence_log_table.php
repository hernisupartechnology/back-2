<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log histórico de la cadena de recurrencias de citas.
 * Registra cada instancia generada de una cita recurrente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_recurrence_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->integer('recurrence_number');
            $table->dateTime('scheduled_date')->nullable();
            $table->enum('status', ['generada', 'programada', 'realizada', 'cancelada', 'no_asistio'])->default('generada');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('parent_appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_recurrence_log');
    }
};
