<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Alergias activas del miembro — se muestran como chips en el historial. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['medicamento', 'alimento', 'ambiental', 'otro'])->default('medicamento');
            $table->string('name');
            $table->text('reaction')->nullable();
            $table->enum('severity', ['leve', 'moderada', 'grave'])->default('leve');
            $table->date('diagnosed_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergies');
    }
};
