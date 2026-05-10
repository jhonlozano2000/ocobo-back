<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_reci_contenido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('mi_bandeja_temp_reci_documentos')->onDelete('cascade');
            $table->json('contenido_yjs');
            $table->string('hash_contenido')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('documento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_reci_contenido');
    }
};