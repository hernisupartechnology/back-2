<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Condiciones crónicas del miembro — se muestran como chips en el historial. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chronic_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('diagnosed_date')->nullable();
            $table->foreignId('treating_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chronic_conditions');
    }
};
