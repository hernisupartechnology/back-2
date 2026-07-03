<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Registro de vacunas por miembro del hogar. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->constrained('users');

            $table->string('vaccine_name');
            $table->string('dose_number')->nullable(); // "1ra dosis", "Refuerzo anual"
            $table->date('application_date');
            $table->string('applied_by')->nullable(); // profesional o centro
            $table->string('lot_number')->nullable(); // número de lote (trazabilidad)
            $table->string('ips_or_center')->nullable();
            $table->date('next_dose_date')->nullable(); // próxima dosis programada
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'next_dose_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccinations');
    }
};
