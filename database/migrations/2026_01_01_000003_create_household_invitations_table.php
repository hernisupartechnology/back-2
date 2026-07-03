<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitaciones a hogares — permite unirse con token de 8 caracteres o via email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('token', 8)->unique(); // código de 8 caracteres para menores
            $table->enum('role_assigned', ['member', 'viewer'])->default('member');
            $table->enum('status', ['pending', 'accepted', 'expired'])->default('pending');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['token', 'status']);
            $table->index('household_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_invitations');
    }
};
