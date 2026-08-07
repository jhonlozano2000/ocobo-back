<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro histórico de cambios de clasificación documental de un radicado.
     */
    public function up(): void
    {
        Schema::create('ventanilla_radica_historial_clasificacion_documental', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('radicado_id');
            $table->unsignedBigInteger('clasificacion_anterior_id')->nullable();
            $table->unsignedBigInteger('clasificacion_nueva_id');
            $table->text('motivo');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('radicado_id', 'vrhcd_radicado_fk')
                ->references('id')
                ->on('ventanilla_radica_reci')
                ->cascadeOnDelete();

            $table->foreign('clasificacion_anterior_id', 'vrhcd_ant_fk')
                ->references('id')
                ->on('clasificacion_documental_trd')
                ->nullOnDelete();

            $table->foreign('clasificacion_nueva_id', 'vrhcd_nueva_fk')
                ->references('id')
                ->on('clasificacion_documental_trd')
                ->restrictOnDelete();

            $table->foreign('user_id', 'vrhcd_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('radicado_id', 'vrhcd_radicado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_historial_clasificacion_documental');
    }
};
