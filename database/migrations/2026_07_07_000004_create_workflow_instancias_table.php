<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instancias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->onDelete('cascade');
            $table->foreignId('nodo_actual_id')->nullable()->constrained('workflow_nodos')->nullOnDelete();
            $table->enum('estado', ['en_curso', 'completada', 'detenida', 'cancelada'])->default('en_curso');
            $table->unsignedBigInteger('usuario_ejecuta_id');
            $table->foreign('usuario_ejecuta_id')->references('id')->on('users');
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->json('datos_contexto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instancias');
    }
};
