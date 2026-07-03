<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Remisiones a especialistas con seguimiento de autorización EPS. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->constrained('users');

            $table->string('specialty');
            $table->foreignId('referring_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('referred_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->text('reason');
            $table->enum('urgency', ['rutina', 'prioritaria', 'urgente'])->default('rutina');

            $table->enum('status', [
                'emitida', 'en_autorizacion', 'autorizada', 'negada',
                'cita_agendada', 'completada', 'vencida',
            ])->default('emitida');

            $table->text('denied_reason')->nullable();
            $table->date('authorization_date')->nullable();
            $table->string('authorization_number')->nullable();
            $table->date('authorization_expiry_date')->nullable(); // cuándo vence la autorización

            // Cita con el especialista (se crea en appointments)
            $table->foreignId('scheduled_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['household_id', 'user_id', 'status']);
            $table->index(['authorization_expiry_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
