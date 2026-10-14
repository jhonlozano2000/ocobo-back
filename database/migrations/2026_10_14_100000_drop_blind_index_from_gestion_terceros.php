<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M16-03: el cifrado de PII de terceros se deja como riesgo aceptado. El blind index del
 * documento no lo usaba ninguna búsqueda (todas son LIKE parciales) y solo duplicaba el dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('gestion_terceros', 'num_docu_nit_hash')) {
            Schema::table('gestion_terceros', function (Blueprint $table) {
                $table->dropIndex(['num_docu_nit_hash']);
                $table->dropColumn('num_docu_nit_hash');
            });
        }
    }

    public function down(): void
    {
        // Sin reversa: la migración 2026_10_12_190000 sigue en el historial y su down() es idempotente.
    }
};
