<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clasificacion_documental_trd', function (Blueprint $table) {
            $table->string('disposicion_final', 5)->nullable()->after('s');
            $table->string('soporte', 15)->nullable()->after('mixto');
        });

        DB::statement("
            UPDATE clasificacion_documental_trd
            SET disposicion_final = CASE
                WHEN ct = 1 AND e = 0 AND m_d = 0 AND s = 0 THEN 'CT'
                WHEN e = 1 AND ct = 0 AND m_d = 0 AND s = 0 THEN 'E'
                WHEN m_d = 1 AND ct = 0 AND e = 0 AND s = 0 THEN 'M'
                WHEN s = 1 AND ct = 0 AND e = 0 AND m_d = 0 THEN 'S'
                WHEN ct = 1 AND e = 1 THEN 'CT'
                WHEN ct = 1 AND m_d = 1 THEN 'CT'
                WHEN ct = 1 AND s = 1 THEN 'CT'
                ELSE NULL
            END
        ");

        DB::statement("
            UPDATE clasificacion_documental_trd
            SET soporte = CASE
                WHEN papel = 1 AND electronico = 0 AND mixto = 0 THEN 'papel'
                WHEN electronico = 1 AND papel = 0 AND mixto = 0 THEN 'electronico'
                WHEN mixto = 1 THEN 'ambos'
                WHEN papel = 1 AND electronico = 1 THEN 'ambos'
                ELSE NULL
            END
        ");
    }

    public function down(): void
    {
        Schema::table('clasificacion_documental_trd', function (Blueprint $table) {
            $table->dropColumn('disposicion_final');
            $table->dropColumn('soporte');
        });
    }
};
