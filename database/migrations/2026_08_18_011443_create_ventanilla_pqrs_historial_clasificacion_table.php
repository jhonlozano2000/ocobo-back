<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Registro histórico de cambios de clasificación documental de un PQRS.
     * Equivalente a ventanilla_radica_historial_clasificacion_documental
     */
    public function up(): void
    {
        Schema::create('ventanilla_pqrs_historial_clasificacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pqrs_id');
            $table->unsignedBigInteger('clasificacion_anterior_id')->nullable();
            $table->unsignedBigInteger('clasificacion_nueva_id');
            $table->text('motivo');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('pqrs_id', 'pqrs_hc_pqrs_fk')
                ->references('id')
                ->on('ventanilla_pqrs')
                ->cascadeOnDelete();

            $table->foreign('clasificacion_anterior_id', 'pqrs_hc_ant_fk')
                ->references('id')
                ->on('clasificacion_documental_trd')
                ->nullOnDelete();

            $table->foreign('clasificacion_nueva_id', 'pqrs_hc_nueva_fk')
                ->references('id')
                ->on('clasificacion_documental_trd')
                ->restrictOnDelete();

            $table->foreign('user_id', 'pqrs_hc_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('pqrs_id', 'pqrs_hc_pqrs_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_pqrs_historial_clasificacion');
    }
};
