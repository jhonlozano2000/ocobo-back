<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_reci_sugerencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('mi_bandeja_temp_reci_documentos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('mi_bandeja_temp_reci_sugerencias')->onDelete('cascade');
            $table->enum('tipo', ['insercion', 'eliminacion', 'reemplazo', 'formato']);
            $table->string('texto_original')->nullable();
            $table->string('texto_sugerido')->nullable();
            $table->json('posicion')->nullable();
            $table->text('justificacion')->nullable();
            $table->enum('estado', ['pendiente', 'aceptada', 'rechazada'])->default('pendiente');
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('fecha_resolucion')->nullable();
            $table->timestamps();

            $table->index('documento_id');
            $table->index('user_id');
            $table->index('estado');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_reci_sugerencias');
    }
};
