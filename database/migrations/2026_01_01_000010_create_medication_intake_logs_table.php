<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro individual de cada toma de medicamento.
 * El Observer calcula delay_minutes y status automáticamente al crear/actualizar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_intake_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medication_schedule_id')->nullable()->constrained('medication_schedules')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // paciente
            $table->foreignId('registered_by')->constrained('users'); // quien registró (puede ser el padre)
            $table->dateTime('scheduled_datetime'); // cuándo debía tomarse
            $table->dateTime('taken_at')->nullable(); // cuándo se tomó realmente
            $table->enum('status', ['tomado', 'omitido', 'atrasado', 'pospuesto'])->default('tomado');
            $table->integer('delay_minutes')->nullable(); // calculado por Observer
            $table->string('dose_taken')->nullable(); // si tomó dosis diferente
            $table->text('notes')->nullable();
            $table->timestamps();

            // Índice compuesto para verificar duplicados en el job de recordatorios
            $table->unique(['medication_id', 'scheduled_datetime', 'user_id'], 'unique_intake_per_schedule');
            $table->index(['user_id', 'scheduled_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_intake_logs');
    }
};
