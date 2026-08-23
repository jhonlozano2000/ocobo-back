<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina las tablas del subsistema de responsables/pases/compartidos PQRS.
     *
     * Flujo real: toda PQRS nace de un radicado recibido y sus responsables,
     * pases, compartidos y archivos viven en el RADICADO (ventanilla_radica_reci*).
     *
     * @author Jhon Javer Lozano Arce
     * @date 2026-08-23
     */
    public function up(): void
    {
        Schema::dropIfExists('ventanilla_pqrs_responsables');
        Schema::dropIfExists('ventanilla_pqrs_pase_historial');
        Schema::dropIfExists('ventanilla_pqrs_compartir_historial');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Sin restauración: las migraciones originales 2026_08_18_011407/011416/011429
        // recrearían tablas de un subsistema eliminado por decisión de diseño.
    }
};
