<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanilla_pqrs', function (Blueprint $table) {
            $table->unsignedBigInteger('gestion_tercero_id')->nullable()->after('ventanilla_radica_reci_id');
            $table->foreign('gestion_tercero_id')
                ->references('id')
                ->on('gestion_terceros')
                ->onDelete('set null');

            $table->unsignedBigInteger('clasificacion_documental_trd_id')->nullable()->after('tipo_pqrs_id');
            $table->foreign('clasificacion_documental_trd_id')
                ->references('id')
                ->on('clasificacion_documental_trd')
                ->onDelete('set null');

            $table->unsignedBigInteger('config_divi_poli_id_afectado')->nullable()->after('clasificacion_documental_trd_id');
            $table->foreign('config_divi_poli_id_afectado')
                ->references('id')
                ->on('config_divi_poli')
                ->onDelete('set null');

            $table->datetime('fecha_respuesta')->nullable()->after('fecha_vencimiento_original');
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_pqrs', function (Blueprint $table) {
            $table->dropForeign(['gestion_tercero_id']);
            $table->dropForeign(['clasificacion_documental_trd_id']);
            $table->dropForeign(['config_divi_poli_id_afectado']);
            $table->dropColumn([
                'gestion_tercero_id',
                'clasificacion_documental_trd_id',
                'config_divi_poli_id_afectado',
                'fecha_respuesta',
            ]);
        });
    }
};
