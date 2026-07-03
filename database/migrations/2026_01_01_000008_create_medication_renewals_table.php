<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Historial de renovaciones de medicamentos crónicos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained()->cascadeOnDelete();
            $table->integer('renewal_number');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['pendiente', 'en_autorizacion', 'autorizado', 'negado', 'reclamado', 'completado'])->default('pendiente');
            $table->date('authorization_date')->nullable();
            $table->string('authorization_number')->nullable();
            $table->date('claimed_date')->nullable();
            $table->timestamp('alert_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('medication_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_renewals');
    }
};
