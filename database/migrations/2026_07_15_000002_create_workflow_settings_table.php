<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->unique()->constrained('workflows')->cascadeOnDelete();
            $table->enum('estrategia_asignacion', ['fila', 'manual', 'autoasignar', 'automatizacion'])
                ->default('manual')
                ->comment('Estrategia de asignación de tareas del flujo');
            $table->json('configuracion_asignacion_json')->nullable()
                ->comment('IDs del equipo, reglas de asignación, o configuración de automatización');
            $table->json('alertas_kpi_json')->nullable()
                ->comment('Umbrales: tareas_pendientes, en_progreso, porcentaje_eficiencia');
            $table->json('opciones_adicionales_json')->nullable()
                ->comment('Booleanos: cambio_fecha_limite, alerta_punto_intermedio, aprobacion_creador');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_settings');
    }
};
