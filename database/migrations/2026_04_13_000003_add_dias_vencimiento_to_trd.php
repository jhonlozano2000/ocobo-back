<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clasificacion_documental_trd', function (Blueprint $table) {
            $table->integer('dias_vencimiento')
                ->nullable()
                ->after('procedimiento')
                ->comment('Días de vencimiento para este tipo documental (hereda si es null)');
        });
    }

    public function down(): void
    {
        Schema::table('clasificacion_documental_trd', function (Blueprint $table) {
            $table->dropColumn('dias_vencimiento');
        });
    }
};
