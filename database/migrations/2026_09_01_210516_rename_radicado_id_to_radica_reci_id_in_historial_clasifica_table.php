<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renombra la columna `radicado_id` a `radica_reci_id` en la tabla
     * `ventanilla_radica_reci_historial_clasifica` para alinearla con el modelo
     * y el patrón usado en todas las demás tablas de ventanilla.
     */
    public function up(): void
    {
        Schema::table('ventanilla_radica_reci_historial_clasifica', function (Blueprint $table) {
            $table->renameColumn('radicado_id', 'radica_reci_id');
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_radica_reci_historial_clasifica', function (Blueprint $table) {
            $table->renameColumn('radica_reci_id', 'radicado_id');
        });
    }
};
