<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M19-29: el barrido horario de vencimientos filtra por estado y fecha_limite; sin índice era full scan.
 */
return new class extends Migration
{
    private const TABLAS = ['workflow_tareas', 'work_flow_tareas'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->index(['estado', 'fecha_limite']));
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropIndex(['estado', 'fecha_limite']));
        }
    }
};
