<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_reci_documento_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')
                ->constrained('mi_bandeja_temp_reci_documentos')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('mi_bandeja_temp_reci_documento_comentarios')
                ->onDelete('cascade');
            $table->text('contenido');
            $table->unsignedInteger('posicion_inicio')->nullable();
            $table->unsignedInteger('posicion_fin')->nullable();
            $table->string('texto_seleccionado', 1000)->nullable();
            $table->boolean('resuelto')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['documento_id', 'resuelto'], 'documento_resuelto_index');
            $table->index(['parent_id'], 'parent_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_reci_documento_comentarios');
    }
};
