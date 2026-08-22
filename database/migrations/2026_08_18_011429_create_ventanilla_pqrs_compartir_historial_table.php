<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla que almacena el historial inmutable de "compartir" (con copia / CC)
     * realizados sobre un PQRS. Equivalente a ventanilla_radica_reci_compartir_historial
     */
    public function up(): void
    {
        Schema::create('ventanilla_pqrs_compartir_historial', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('pqrs_id');
            $table->foreign('pqrs_id', 'pqrs_compartir_hist_pqrs_fk')->references('id')->on('ventanilla_pqrs');

            $table->unsignedBigInteger('usuario_origen_id')->nullable();
            $table->foreign('usuario_origen_id', 'pqrs_compartir_hist_usuario_orig_fk')->references('id')->on('users');

            $table->unsignedBigInteger('users_cargos_destino_id');
            $table->foreign('users_cargos_destino_id', 'pqrs_compartir_hist_users_cargos_fk')->references('id')->on('users_cargos');

            $table->unsignedBigInteger('usuario_destino_id');
            $table->foreign('usuario_destino_id', 'pqrs_compartir_hist_usuario_dest_fk')->references('id')->on('users');

            $table->timestamps();

            $table->index(['pqrs_id', 'created_at'], 'pqrs_compartir_hist_pqrs_fecha_idx');
            $table->index('usuario_destino_id', 'pqrs_compartir_hist_usuario_dest_idx');
            $table->index('usuario_origen_id', 'pqrs_compartir_hist_usuario_orig_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_pqrs_compartir_historial');
    }
};
