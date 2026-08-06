<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $foreignKeys = DB::select('SHOW CREATE TABLE ventanilla_radica_interno_archivos');
        $createSql = $foreignKeys[0]->{'Create Table'} ?? '';

        if (str_contains($createSql, 'ventanilla_radica_interno_archivos_radicado_id_foreign')) {
            return;
        }

        Schema::table('ventanilla_radica_interno_archivos', function (Blueprint $table) {
            if (Schema::hasIndex('ventanilla_radica_interno_archivos', 'ventanilla_radica_interno_archivos_radicado_id_index')) {
                $table->dropIndex('ventanilla_radica_interno_archivos_radicado_id_index');
            }
            $table->foreign('radicado_id')->references('id')->on('ventanilla_radica_internos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_radica_interno_archivos', function (Blueprint $table) {
            $table->dropForeign(['radicado_id']);
            $table->index('radicado_id');
        });
    }
};
