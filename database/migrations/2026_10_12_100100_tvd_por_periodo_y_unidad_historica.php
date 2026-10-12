<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M11-02: los elementos de la TVD cuelgan del período y de la unidad administrativa histórica en
 * lugar de la dependencia actual; la aprobación pasa al período (fondo_acumulado_periodos).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Una dependencia actual no equivale a una unidad histórica: no hay conversión automática
        if (DB::table('clasificacion_documental_tvd')->exists()) {
            throw new RuntimeException(
                'clasificacion_documental_tvd tiene registros. Reasígnelos a un fondo, período y unidad histórica '
                .'(o elimínelos) antes de migrar: la TVD ya no se registra por dependencia actual.'
            );
        }

        Schema::table('clasificacion_documental_tvd', function (Blueprint $table) {
            $table->dropForeign(['dependencia_id']);
            $table->dropIndex(['dependencia_id']);
            $table->dropColumn('dependencia_id');
            $table->dropConstrainedForeignId('aprobado_por');
            $table->dropColumn('fecha_aprobacion');

            $table->foreignId('periodo_id')->after('parent')->constrained('fondo_acumulado_periodos')->restrictOnDelete();
            $table->foreignId('unidad_id')->after('periodo_id')->constrained('fondo_acumulado_unidades')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('clasificacion_documental_tvd')->exists()) {
            throw new RuntimeException('clasificacion_documental_tvd tiene registros por período; vacíela antes de revertir.');
        }

        Schema::table('clasificacion_documental_tvd', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unidad_id');
            $table->dropConstrainedForeignId('periodo_id');

            $table->unsignedBigInteger('dependencia_id')->nullable()->after('parent');
            $table->foreign('dependencia_id')->references('id')->on('calidad_organigrama')->onDelete('set null');
            $table->index('dependencia_id');
            $table->foreignId('aprobado_por')->nullable()->after('estado')->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_aprobacion')->nullable()->after('aprobado_por');
        });
    }
};
