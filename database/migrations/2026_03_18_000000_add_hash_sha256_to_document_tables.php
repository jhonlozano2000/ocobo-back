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
        $tables = [
            'ventanilla_radica_reci',
            'ventanilla_radica_reci_archivos',
            'ventanilla_radica_enviados',
            'ventanilla_radica_enviados_archivos'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'hash_sha256')) {
                    $table->string('hash_sha256', 64)->nullable()->after('archivo_digital')->comment('Hash SHA-256 para integridad documental');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'ventanilla_radica_reci',
            'ventanilla_radica_reci_archivos',
            'ventanilla_radica_enviados',
            'ventanilla_radica_enviados_archivos'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('hash_sha256');
            });
        }
    }
};
