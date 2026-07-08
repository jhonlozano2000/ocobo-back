<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_flow_tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nodo_id')->constrained('workflow_nodos')->cascadeOnDelete();
            $table->foreignId('instancia_id')->nullable()->constrained('workflow_instancias')->nullOnDelete();
            $table->foreignId('responsable_usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo', 255);
            $table->text('descripcion')->nullable();
            $table->text('instrucciones')->nullable();
            $table->unsignedSmallInteger('tiempo_limite_horas')->nullable();
            $table->datetime('fecha_limite')->nullable();
            $table->enum('estado', ['pendiente', 'en_curso', 'completada', 'vencida', 'cancelada'])
                ->default('pendiente');
            $table->boolean('adjuntos_permitidos')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->json('resultado_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_flow_tareas');
    }
};
