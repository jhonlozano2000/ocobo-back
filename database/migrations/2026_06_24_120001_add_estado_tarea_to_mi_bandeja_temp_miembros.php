<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mi_bandeja_temp_grupo_responsables', function (Blueprint $table) {
            $table->string('estado_tarea', 20)->default('pendiente')->after('descargo_plantilla');
        });

        Schema::table('mi_bandeja_temp_grupo_firmantes', function (Blueprint $table) {
            $table->string('estado_tarea', 20)->default('pendiente')->after('fechor_firmado');
        });

        Schema::table('mi_bandeja_temp_grupo_proyectores', function (Blueprint $table) {
            $table->string('estado_tarea', 20)->default('pendiente')->after('descargo_plantilla');
        });
    }

    public function down(): void
    {
        Schema::table('mi_bandeja_temp_grupo_proyectores', function (Blueprint $table) {
            $table->dropColumn('estado_tarea');
        });
        Schema::table('mi_bandeja_temp_grupo_firmantes', function (Blueprint $table) {
            $table->dropColumn('estado_tarea');
        });
        Schema::table('mi_bandeja_temp_grupo_responsables', function (Blueprint $table) {
            $table->dropColumn('estado_tarea');
        });
    }
};
