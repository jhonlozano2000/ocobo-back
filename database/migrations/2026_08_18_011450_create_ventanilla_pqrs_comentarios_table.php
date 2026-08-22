<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Comentarios para PQRS con soporte para hilos (threaded replies)
     * Equivalente a mi_bandeja_temp_reci_comentarios
     */
    public function up(): void
    {
        Schema::create('ventanilla_pqrs_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pqrs_id')->constrained('ventanilla_pqrs')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('ventanilla_pqrs_comentarios')->onDelete('cascade');
            $table->text('contenido');
            $table->json('seleccion_texto')->nullable();
            $table->boolean('resuelto')->default(false);
            $table->boolean('es_nota_interna')->default(false);
            $table->timestamps();

            $table->index('pqrs_id');
            $table->index('user_id');
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_pqrs_comentarios');
    }
};
