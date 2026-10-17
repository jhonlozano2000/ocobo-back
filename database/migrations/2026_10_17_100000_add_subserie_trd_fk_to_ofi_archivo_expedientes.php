<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * subserie_trd_id se creó como entero suelto, sin FK; serie_trd_id sí la tiene.
 * Se alinea con la misma restricción (no se borra una TRD referenciada por un expediente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->foreign('subserie_trd_id')->references('id')->on('clasificacion_documental_trd');
        });
    }

    public function down(): void
    {
        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->dropForeign(['subserie_trd_id']);
        });
    }
};
