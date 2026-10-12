<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M01-20 / M03-22: los tableros y bandejas filtran por estado_trabajo + fec_venci y ordenan
 * por created_at sin índices; con volumen real cada consulta recorría la tabla completa.
 */
return new class extends Migration
{
    private const TABLAS = [
        'ventanilla_radica_reci' => 'vrr',
        'ventanilla_radica_internos' => 'vri',
        'ventanilla_radica_enviados' => 'vre',
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla => $prefijo) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($tabla, $prefijo) {
                if (Schema::hasColumns($tabla, ['estado_trabajo', 'fec_venci']) && ! Schema::hasIndex($tabla, "{$prefijo}_estado_venci_idx")) {
                    $table->index(['estado_trabajo', 'fec_venci'], "{$prefijo}_estado_venci_idx");
                }
                if (Schema::hasColumn($tabla, 'created_at') && ! Schema::hasIndex($tabla, "{$prefijo}_created_at_idx")) {
                    $table->index('created_at', "{$prefijo}_created_at_idx");
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla => $prefijo) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($tabla, $prefijo) {
                foreach (["{$prefijo}_estado_venci_idx", "{$prefijo}_created_at_idx"] as $indice) {
                    if (Schema::hasIndex($tabla, $indice)) {
                        $table->dropIndex($indice);
                    }
                }
            });
        }
    }
};
