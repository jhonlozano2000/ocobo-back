<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanilla_radica_reci', function (Blueprint $table) {
            $table->string('estado_trabajo', 30)
                ->default('RECIBIDO')
                ->comment('Estado del flujo de trabajo: RECIBIDO, EN_PROCESO, POR_VENCER, VENCIDO, FINALIZADO')
                ->after('impri_rotulo');

            $table->index('estado_trabajo', 'idx_estado_trabajo');
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_radica_reci', function (Blueprint $table) {
            $table->dropIndex('idx_estado_trabajo');
            $table->dropColumn('estado_trabajo');
        });
    }
};
