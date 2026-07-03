<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos de hogar y perfil médico a la tabla users.
 * Se ejecuta después de crear households para poder referenciar la FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Rol dentro del sistema
            $table->enum('role', ['owner', 'member', 'viewer'])->default('member')->after('avatar');

            // Hogar al que pertenece
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete()->after('role');

            // Datos de contacto y personales
            $table->string('phone', 20)->nullable()->after('household_id');
            $table->date('birthdate')->nullable()->after('phone');
            $table->enum('gender', ['masculino', 'femenino', 'otro'])->nullable()->after('birthdate');

            // Datos médicos
            $table->enum('blood_type', ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])->nullable()->after('gender');
            $table->string('eps')->nullable()->after('blood_type');
            $table->string('ips_preferida')->nullable()->after('eps');
            $table->string('numero_afiliado')->nullable()->after('ips_preferida');

            // Control de menores de edad
            $table->boolean('is_minor')->default(false)->after('numero_afiliado');
            $table->foreignId('supervised_by')->nullable()->constrained('users')->nullOnDelete()->after('is_minor');

            // Preferencias de salud
            $table->boolean('track_vital_signs')->default(false)->after('supervised_by');

            // Configuración de modo oscuro
            $table->boolean('dark_mode')->default(false)->after('track_vital_signs');
        });

        // Agregar FK de households.owner_id a users ahora que users ya tiene todos los campos
        Schema::table('households', function (Blueprint $table) {
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['household_id']);
            $table->dropForeign(['supervised_by']);
            $table->dropColumn([
                'role', 'household_id', 'phone', 'birthdate', 'gender',
                'blood_type', 'eps', 'ips_preferida', 'numero_afiliado',
                'is_minor', 'supervised_by', 'track_vital_signs', 'dark_mode',
            ]);
        });
    }
};
