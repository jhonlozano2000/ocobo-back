<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M19-27: ramas paralelas. Nodos "paralelo" (activa todas sus salidas) y "union" (espera a que
 * terminen las ramas); el nodo de unión queda "en_espera" mientras alguna rama puede llegar a él.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE workflow_nodos MODIFY tipo ENUM('inicio','tarea','condicion','notificacion','fin','paralelo','union') NOT NULL");
        DB::statement("ALTER TABLE workflow_nodos_instancia MODIFY estado ENUM('pendiente','en_curso','en_espera','completado','saltado') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        DB::statement("UPDATE workflow_nodos_instancia SET estado = 'pendiente' WHERE estado = 'en_espera'");
        DB::statement("ALTER TABLE workflow_nodos_instancia MODIFY estado ENUM('pendiente','en_curso','completado','saltado') NOT NULL DEFAULT 'pendiente'");
        DB::statement("ALTER TABLE workflow_nodos MODIFY tipo ENUM('inicio','tarea','condicion','notificacion','fin') NOT NULL");
    }
};
