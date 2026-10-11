<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aprobación de la TVD por dependencia (M11-03): una vez aprobada es inmutable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clasificacion_documental_tvd', function (Blueprint $table) {
            $table->foreignId('aprobado_por')->nullable()->after('estado')->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_aprobacion')->nullable()->after('aprobado_por');
        });
    }

    public function down(): void
    {
        Schema::table('clasificacion_documental_tvd', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprobado_por');
            $table->dropColumn('fecha_aprobacion');
        });
    }
};
