<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_reci_documento_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')
                ->constrained('mi_bandeja_temp_reci_documentos')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->unsignedInteger('version');
            $table->json('contenido')->nullable();
            $table->string('hash_contenido', 64)->nullable();
            $table->string('descripcion', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['documento_id', 'version'], 'idx_doc_version');
            $table->unique(['documento_id', 'version'], 'uniq_doc_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_reci_documento_versiones');
    }
};
