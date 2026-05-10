<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_reci_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('mi_bandeja_temp_reci_documentos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('mi_bandeja_temp_reci_comentarios')->onDelete('cascade');
            $table->text('contenido');
            $table->json('seleccion_texto')->nullable();
            $table->boolean('resuelto')->default(false);
            $table->timestamps();

            $table->index('documento_id');
            $table->index('user_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_reci_comentarios');
    }
};