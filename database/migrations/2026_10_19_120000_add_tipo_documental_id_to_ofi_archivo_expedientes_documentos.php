<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B07 (diseño de expedientes): el tipo documental de cada folio sale del catálogo de la TRD
     * (nodos TipoDocumento de la subserie, o de la serie si no tiene subseries) y deja de ser
     * texto libre. `tipo_documental` se conserva como el nombre vigente al incorporar.
     *
     * Sin FK: el folio guarda el nodo de la versión de TRD con la que se tipificó, aunque esa
     * versión se reemplace después.
     */
    public function up(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_documental_id')->nullable()->after('tipo_documental')->index()
                ->comment('Nodo TipoDocumento de clasificacion_documental_trd; nulo = pendiente de tipificar');
        });

        // Los folios cuyo texto coincide con un tipo documental de su serie o subserie quedan tipificados
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE ofi_archivo_expedientes_documentos d
                JOIN ofi_archivo_expedientes e ON e.id = d.expediente_id
                JOIN clasificacion_documental_trd t
                    ON t.parent = COALESCE(e.subserie_trd_id, e.serie_trd_id)
                    AND t.tipo = 'TipoDocumento' AND t.nom = d.tipo_documental
                SET d.tipo_documental_id = t.id
                WHERE d.tipo = 'tipo_documental' AND d.tipo_documental_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->dropIndex(['tipo_documental_id']);
            $table->dropColumn('tipo_documental_id');
        });
    }
};
