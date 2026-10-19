<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B14 (diseño de expedientes): la retención y la disposición final se rigen por la TRD
     * vigente al cierre [Acuerdo AGN 001/2024 A.4.4.3]. El cierre guarda una instantánea de la
     * subserie (o serie) y las fechas en que se cumple la retención en gestión y en central.
     *
     * Los expedientes ya cerrados quedan sin instantánea; la retención se sigue calculando con
     * la TRD actual hasta un nuevo cierre.
     */
    public function up(): void
    {
        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->json('trd_snapshot')->nullable()->after('hash_indice')
                ->comment('TRD al cierre: versión, retención en gestión y central, disposición');
            $table->date('fecha_fin_gestion')->nullable()->after('trd_snapshot');
            $table->date('fecha_fin_central')->nullable()->after('fecha_fin_gestion');
        });
    }

    public function down(): void
    {
        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->dropColumn(['trd_snapshot', 'fecha_fin_gestion', 'fecha_fin_central']);
        });
    }
};
