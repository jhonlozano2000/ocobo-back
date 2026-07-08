<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->unsignedSmallInteger('fecha_limite_dias')->nullable()
                ->after('configuracion_general')
                ->comment('Días para completar el flujo');
            $table->enum('fecha_limite_tipo', ['naturales', 'habiles'])
                ->default('habiles')
                ->after('fecha_limite_dias')
                ->comment('Tipo de días para el cálculo del vencimiento');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn(['fecha_limite_dias', 'fecha_limite_tipo']);
        });
    }
};
