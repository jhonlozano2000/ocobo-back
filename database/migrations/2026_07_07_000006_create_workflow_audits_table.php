<?php

/**
 * Migración para crear la tabla de auditoría de Workflows (ISO 27001 A.12.4.1).
 * Tabla de solo INSERT - traza forense inmutable de acciones críticas.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instancia_id')->nullable()->constrained('workflow_instancias')->nullOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('action');
            $table->json('payload_json');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['workflow_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_audits');
    }
};
