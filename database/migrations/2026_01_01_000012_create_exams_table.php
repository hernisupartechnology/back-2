<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de exámenes médicos — flujo desde sin_orden hasta entregado_medico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->constrained('users');

            $table->string('name');
            $table->enum('exam_type', ['laboratorio', 'imagen', 'especializado', 'otro'])->default('laboratorio');
            $table->string('lab_or_center')->nullable();
            $table->enum('urgency', ['rutina', 'urgente'])->default('rutina');

            $table->enum('status', [
                'sin_orden', 'con_orden', 'en_autorizacion', 'autorizado', 'negado',
                'agendado', 'muestra_tomada', 'resultado_pendiente', 'resultado_disponible',
                'entregado_medico', 'cancelado',
            ])->default('con_orden');

            $table->text('denied_reason')->nullable();
            $table->date('authorization_date')->nullable();
            $table->string('authorization_number')->nullable();
            $table->dateTime('scheduled_date')->nullable(); // fecha de la cita del examen
            $table->date('performed_date')->nullable();
            $table->date('result_date')->nullable();
            $table->text('result_summary')->nullable();
            $table->date('delivered_to_doctor_date')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['household_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
