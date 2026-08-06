<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Migrar datos existentes: en_progreso → en_curso (ambas tablas)
        foreach (['workflow_tareas', 'work_flow_tareas'] as $table) {
            DB::table($table)
                ->where('estado', 'en_progreso')
                ->update(['estado' => 'en_curso']);
        }

        // 2. Actualizar ENUM de workflow_tareas (5 estados unificados)
        DB::statement("ALTER TABLE workflow_tareas MODIFY COLUMN estado ENUM('pendiente','en_curso','completada','vencida','cancelada') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        // Restaurar ENUM anterior + revertir datos
        DB::table('workflow_tareas')
            ->where('estado', 'en_curso')
            ->update(['estado' => 'en_progreso']);

        DB::table('work_flow_tareas')
            ->where('estado', 'en_curso')
            ->update(['estado' => 'en_progreso']);

        DB::statement("ALTER TABLE workflow_tareas MODIFY COLUMN estado ENUM('pendiente','en_progreso','completada') NOT NULL DEFAULT 'pendiente'");
    }
};
