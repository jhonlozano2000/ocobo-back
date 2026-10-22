<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formato del documento en Mis Documentos (estilos modificados: Normal, Título 1-3 y Cita), aparte de la
 * configuración de página.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mi_bandeja_temp_reci_documentos', function (Blueprint $table) {
            $table->json('configuracion_formato')->nullable()->after('configuracion_footer');
        });
    }

    public function down(): void
    {
        Schema::table('mi_bandeja_temp_reci_documentos', function (Blueprint $table) {
            $table->dropColumn('configuracion_formato');
        });
    }
};
