<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signos vitales para seguimiento de pacientes crónicos.
 * Los rangos normales son configurables por miembro en la tabla vital_sign_ranges.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->constrained('users');
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();

            $table->dateTime('measurement_date');
            $table->integer('systolic_pressure')->nullable();    // presión sistólica (mmHg)
            $table->integer('diastolic_pressure')->nullable();  // presión diastólica (mmHg)
            $table->integer('heart_rate')->nullable();          // frecuencia cardíaca (lpm)
            $table->decimal('blood_glucose', 5, 1)->nullable(); // glucosa (mg/dL)
            $table->decimal('weight', 5, 2)->nullable();        // peso (kg)
            $table->decimal('height', 4, 1)->nullable();        // talla (cm)
            $table->decimal('temperature', 4, 1)->nullable();   // temperatura (°C)
            $table->tinyInteger('oxygen_saturation')->nullable(); // saturación O2 (%)
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->index(['user_id', 'measurement_date']);
        });

        // Rangos normales personalizables por miembro
        Schema::create('vital_sign_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Presión arterial
            $table->integer('systolic_min')->default(90);
            $table->integer('systolic_max')->default(139);
            $table->integer('diastolic_min')->default(60);
            $table->integer('diastolic_max')->default(89);
            // Glucosa
            $table->decimal('glucose_min', 5, 1)->default(70);
            $table->decimal('glucose_max', 5, 1)->default(140);
            // Saturación
            $table->tinyInteger('oxygen_min')->default(94);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vital_signs');
        Schema::dropIfExists('vital_sign_ranges');
    }
};
