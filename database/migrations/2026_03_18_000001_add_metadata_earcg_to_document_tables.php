<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['ventanilla_radica_reci', 'ventanilla_radica_enviados'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'soporte')) {
                    $table->enum('soporte', ['Papel', 'Electronico', 'Hibrido'])->default('Electronico')->after('archivo_digital')->comment('Soporte del documento original (Acuerdo 003/2015 AGN)');
                }
                if (!Schema::hasColumn($table->getTable(), 'archivo_tipo')) {
                    $table->string('archivo_tipo', 100)->nullable()->after('soporte')->comment('MIME type del archivo para preservacion a largo plazo');
                }
                if (!Schema::hasColumn($table->getTable(), 'archivo_peso')) {
                    $table->unsignedBigInteger('archivo_peso')->nullable()->after('archivo_tipo')->comment('Peso en bytes del archivo');
                }
                if (!Schema::hasColumn($table->getTable(), 'fec_radicado')) {
                    $table->timestamp('fec_radicado')->nullable()->after('num_radicado')->comment('Fecha y hora oficial de radicacion (Acuerdo 060/2001 AGN)');
                }
            });
        }

        // También para archivos adjuntos
        $adjuntoTables = ['ventanilla_radica_reci_archivos', 'ventanilla_radica_enviados_archivos'];
        foreach ($adjuntoTables as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'archivo_tipo')) {
                    $table->string('archivo_tipo', 100)->nullable()->after('archivo')->comment('MIME type del anexo');
                }
                if (!Schema::hasColumn($table->getTable(), 'archivo_peso')) {
                    $table->unsignedBigInteger('archivo_peso')->nullable()->after('archivo_tipo')->comment('Peso en bytes del anexo');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $allTables = [
            'ventanilla_radica_reci', 
            'ventanilla_radica_enviados',
            'ventanilla_radica_reci_archivos', 
            'ventanilla_radica_enviados_archivos'
        ];

        foreach ($allTables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['soporte', 'archivo_tipo', 'archivo_peso', 'fec_radicado']);
            });
        }
    }
};
