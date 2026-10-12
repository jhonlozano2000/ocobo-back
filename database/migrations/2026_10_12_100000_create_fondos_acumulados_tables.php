<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fondos acumulados (M11-02): la TVD valora la documentación producida antes de la TRD, por lo
 * que se registra por fondo → período histórico → unidad administrativa de ese período, no por la
 * dependencia actual del organigrama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fondos_acumulados', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre');
            $table->string('entidad_productora');
            $table->string('origen', 20)->default('propio'); // propio | recibido (entidad fusionada o liquidada)
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->text('descripcion')->nullable();
            $table->foreignId('user_register')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fondo_acumulado_periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fondo_id')->constrained('fondos_acumulados')->cascadeOnDelete();
            $table->string('nombre');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('acto_administrativo')->nullable(); // acto que fijó la estructura del período
            $table->text('descripcion')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->foreignId('user_register')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fondo_acumulado_unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_id')->constrained('fondo_acumulado_periodos')->cascadeOnDelete();
            $table->string('codigo', 20);
            $table->string('nombre');
            $table->foreignId('parent')->nullable()->constrained('fondo_acumulado_unidades')->nullOnDelete();
            $table->timestamps();

            $table->unique(['periodo_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fondo_acumulado_unidades');
        Schema::dropIfExists('fondo_acumulado_periodos');
        Schema::dropIfExists('fondos_acumulados');
    }
};
