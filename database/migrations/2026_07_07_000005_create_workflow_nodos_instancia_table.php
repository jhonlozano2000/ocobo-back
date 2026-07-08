<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_nodos_instancia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instancia_id')->constrained('workflow_instancias')->onDelete('cascade');
            $table->foreignId('nodo_id')->constrained('workflow_nodos')->onDelete('cascade');
            $table->enum('estado', ['pendiente', 'en_curso', 'completado', 'saltado'])->default('pendiente');
            $table->dateTime('fecha_ejecucion')->nullable();
            $table->json('resultado_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_nodos_instancia');
    }
};
