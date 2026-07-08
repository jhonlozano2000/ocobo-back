<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instancias', function (Blueprint $table) {
            $table->datetime('fecha_limite_estimada')->nullable()
                ->after('fecha_fin')
                ->comment('Fecha límite calculada al iniciar la instancia');
            $table->enum('estado_vencimiento', ['vigente', 'por_vencer', 'vencido', 'cumplido'])
                ->default('vigente')
                ->after('estado')
                ->comment('Estado de vencimiento de la instancia');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instancias', function (Blueprint $table) {
            $table->dropColumn(['fecha_limite_estimada', 'estado_vencimiento']);
        });
    }
};
