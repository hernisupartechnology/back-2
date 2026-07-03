<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de reportes generados por los usuarios.
 * Se mantienen los últimos 10 por usuario; el job limpia automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_type'); // 'individual', 'household', 'alerts', 'leaves'
            $table->string('format'); // 'pdf', 'excel'
            $table->json('parameters')->nullable(); // filtros usados al generar
            $table->string('file_path')->nullable(); // ruta del archivo generado
            $table->string('file_name')->nullable();
            $table->integer('file_size')->nullable(); // en bytes
            $table->enum('status', ['generando', 'completado', 'error'])->default('generando');
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_history');
    }
};
