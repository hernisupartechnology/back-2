<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Habilita "perfiles gestionados" — miembros del hogar (niños, adultos
 * mayores, cualquiera que no vaya a usar la app por su cuenta) creados
 * directamente por el owner, sin correo ni contraseña propios porque nunca
 * inician sesión. email/password dejan de ser obligatorios; is_managed marca
 * explícitamente estos perfiles (más robusto que inferirlo de password=null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->boolean('is_managed')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_managed');
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
