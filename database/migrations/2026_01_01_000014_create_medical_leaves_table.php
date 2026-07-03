<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Incapacidades médicas — total_days calculado automáticamente por Observer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->constrained('users');
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issuing_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->string('issuing_doctor_name_free')->nullable();

            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days'); // calculado: end_date - start_date + 1

            $table->text('diagnosis')->nullable();
            $table->string('diagnosis_code', 10)->nullable(); // código CIE-10

            $table->enum('leave_type', [
                'enfermedad_general', 'accidente_trabajo',
                'licencia_maternidad', 'licencia_paternidad', 'otro',
            ])->default('enfermedad_general');

            $table->string('ips_issued')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['household_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_leaves');
    }
};
