<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de hogares — entidad raíz del sistema.
 * Un hogar agrupa a todos los miembros de la familia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // owner_id se referenciará con unsignedBigInteger para evitar problema de orden
            $table->unsignedBigInteger('owner_id');
            $table->string('avatar')->nullable();
            $table->timestamps();

            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
