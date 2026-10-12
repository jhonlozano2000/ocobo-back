<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M09-06: la TRD aprobada y el expediente guardan el código y el nombre que tenía la dependencia
 * en ese momento; si el organigrama cambia después, conservan la estructura orgánica histórica.
 * Las filas existentes se completan con el valor actual del organigrama (mejor dato disponible).
 */
return new class extends Migration
{
    private const TABLAS = ['clasificacion_documental_trd_versiones', 'ofi_archivo_expedientes'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('dependencia_cod', 10)->nullable()->after('dependencia_id');
                $table->string('dependencia_nom', 100)->nullable()->after('dependencia_cod');
            });
        }

        DB::table('ofi_archivo_expedientes as e')
            ->join('calidad_organigrama as o', 'o.id', '=', 'e.dependencia_id')
            ->update(['e.dependencia_cod' => DB::raw('o.cod_organico'), 'e.dependencia_nom' => DB::raw('o.nom_organico')]);

        DB::table('clasificacion_documental_trd_versiones as v')
            ->join('calidad_organigrama as o', 'o.id', '=', 'v.dependencia_id')
            ->whereIn('v.estado_version', ['ACTIVO', 'HISTORICO'])
            ->update(['v.dependencia_cod' => DB::raw('o.cod_organico'), 'v.dependencia_nom' => DB::raw('o.nom_organico')]);
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn(['dependencia_cod', 'dependencia_nom']);
            });
        }
    }
};
