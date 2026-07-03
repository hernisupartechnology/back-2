<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos médicos adjuntos (imágenes y PDFs).
 * Los archivos se sirven SIEMPRE via controlador autenticado — nunca por rutas públicas.
 * Ruta de almacenamiento: storage/app/private/medical/{household_id}/{user_id}/{year}/
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');

            // Entidad a la que está asociado este documento
            $table->enum('related_type', [
                'appointment', 'medication', 'exam', 'referral',
                'medical_leave', 'vaccination', 'general',
            ])->default('general');
            $table->unsignedBigInteger('related_id')->nullable();

            $table->enum('document_type', [
                'historia_clinica', 'orden_medicamento', 'orden_examen',
                'resultado_examen', 'autorizacion_eps', 'incapacidad',
                'remision', 'vacuna', 'otro',
            ])->default('otro');

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path'); // ruta dentro de storage/app/private
            $table->string('file_name'); // nombre original del archivo
            $table->enum('file_type', ['image', 'pdf'])->default('pdf');
            $table->integer('file_size'); // en bytes
            $table->date('document_date')->nullable(); // fecha en el documento físico

            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->index(['household_id', 'user_id', 'related_type']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_documents');
    }
};
