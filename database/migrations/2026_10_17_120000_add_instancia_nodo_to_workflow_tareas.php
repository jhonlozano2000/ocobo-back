<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M19-17: Tarea es el sistema de tareas canónico. Las tareas que nacen de una instancia (a partir
 * de las plantillas WorkFlowTarea del nodo) guardan la instancia, el nodo y la plantilla de origen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_tareas', function (Blueprint $table) {
            $table->foreignId('instancia_id')->nullable()->after('workflow_id')
                ->constrained('workflow_instancias')->cascadeOnDelete();
            $table->foreignId('nodo_id')->nullable()->after('instancia_id')
                ->constrained('workflow_nodos')->nullOnDelete();
            $table->foreignId('plantilla_id')->nullable()->after('nodo_id')
                ->constrained('work_flow_tareas')->nullOnDelete();
            $table->index(['instancia_id', 'nodo_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('workflow_tareas', function (Blueprint $table) {
            // Primero las FK: MySQL puede estar usando el índice compuesto para la de instancia_id
            $table->dropForeign(['plantilla_id']);
            $table->dropForeign(['nodo_id']);
            $table->dropForeign(['instancia_id']);
            $table->dropIndex(['instancia_id', 'nodo_id', 'estado']);
            $table->dropColumn(['instancia_id', 'nodo_id', 'plantilla_id']);
        });
    }
};
