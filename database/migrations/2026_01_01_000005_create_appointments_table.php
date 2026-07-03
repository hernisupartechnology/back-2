<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla principal de citas médicas y necesidades.
 * Una "necesidad" es una cita que aún no ha sido agendada (is_need=true).
 * Soporta recurrencia (control de enfermedades crónicas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // paciente
            $table->foreignId('registered_by')->constrained('users'); // quién registró
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->string('doctor_name_free')->nullable(); // médico libre (fuera del catálogo)

            $table->string('specialty');
            $table->enum('appointment_type', ['consulta', 'control', 'urgencias', 'domiciliaria', 'telemedicina'])->default('consulta');
            $table->string('ips')->nullable();
            $table->string('address')->nullable();

            // === CAMPOS DE NECESIDAD (sin fecha aún) ===
            $table->boolean('is_need')->default(false);
            $table->text('need_reason')->nullable();
            $table->enum('need_urgency', ['rutina', 'prioritaria', 'urgente'])->default('rutina');
            $table->date('need_registered_date')->nullable();
            $table->integer('max_days_to_schedule')->default(30);
            $table->integer('alert_days_before_scheduling')->default(10);

            // === CAMPOS DE CITA PROGRAMADA ===
            $table->dateTime('appointment_date')->nullable();
            $table->text('reason')->nullable(); // motivo de consulta
            $table->text('diagnosis')->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', [
                'necesidad', 'programada', 'confirmada', 'realizada',
                'cancelada', 'reprogramada', 'no_asistio',
            ])->default('necesidad');

            $table->text('cancelled_reason')->nullable();
            $table->enum('cancelled_by', ['paciente', 'ips', 'eps'])->nullable();

            // === SIGUIENTE CITA SUGERIDA POR EL MÉDICO ===
            $table->date('next_appointment_date')->nullable();
            $table->string('next_appointment_notes')->nullable();
            $table->string('next_appointment_specialty')->nullable();

            // === RECURRENCIA ===
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurrence_type', ['semanal', 'mensual', 'bimestral', 'trimestral', 'semestral', 'anual'])->nullable();
            $table->integer('recurrence_interval_days')->nullable(); // calculado por Observer
            $table->integer('alert_days_before_appointment')->default(3);
            $table->foreignId('parent_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->integer('recurrence_number')->default(1);
            $table->boolean('next_recurrence_generated')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Índices para consultas frecuentes del semáforo
            $table->index(['household_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['appointment_date', 'status']);
            $table->index(['is_need', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
