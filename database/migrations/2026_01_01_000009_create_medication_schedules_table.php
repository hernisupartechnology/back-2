<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horarios programados de toma de medicamentos.
 * Un medicamento puede tener múltiples horarios (ej: 6am, 2pm, 10pm para "cada 8 horas").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->time('time_of_day'); // hora de la toma: "08:00:00"
            $table->string('label')->nullable(); // "Mañana", "Noche", "Antes de dormir"
            $table->json('days_of_week')->nullable(); // null = todos; [1,2,3] = lun, mar, mié
            $table->boolean('is_active')->default(true);
            $table->integer('reminder_minutes_before')->default(5);
            $table->timestamps();

            $table->index(['medication_id', 'is_active']);
            $table->index(['user_id', 'time_of_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_schedules');
    }
};
