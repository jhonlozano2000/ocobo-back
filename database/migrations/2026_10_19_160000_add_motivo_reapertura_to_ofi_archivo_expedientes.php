<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B13 (diseño de expedientes §3.5): la reapertura exige un motivo. Queda en el expediente y,
     * con los datos del cierre que se limpian, en el historial encadenado.
     */
    public function up(): void
    {
        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->string('motivo_reapertura', 500)->nullable()->after('motivo_cierre');
        });
    }

    public function down(): void
    {
        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->dropColumn('motivo_reapertura');
        });
    }
};
