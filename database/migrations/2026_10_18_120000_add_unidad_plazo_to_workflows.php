<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M19-30: los plazos del flujo pueden medirse en horas o en días hábiles (descontando fines de
 * semana y festivos). El valor sigue en las columnas *_horas; la unidad dice cómo leerlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->enum('tiempo_finalizacion_unidad', ['horas', 'dias_habiles'])->default('horas')->after('tiempo_finalizacion_horas');
        });
        Schema::table('workflow_nodos', function (Blueprint $table) {
            $table->enum('tiempo_limite_unidad', ['horas', 'dias_habiles'])->default('horas')->after('tiempo_limite_horas');
        });
        Schema::table('work_flow_tareas', function (Blueprint $table) {
            $table->enum('tiempo_limite_unidad', ['horas', 'dias_habiles'])->default('horas')->after('tiempo_limite_horas');
        });
    }

    public function down(): void
    {
        Schema::table('work_flow_tareas', fn (Blueprint $table) => $table->dropColumn('tiempo_limite_unidad'));
        Schema::table('workflow_nodos', fn (Blueprint $table) => $table->dropColumn('tiempo_limite_unidad'));
        Schema::table('workflows', fn (Blueprint $table) => $table->dropColumn('tiempo_finalizacion_unidad'));
    }
};
