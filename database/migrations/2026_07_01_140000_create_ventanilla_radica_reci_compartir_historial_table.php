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
     * realizados sobre un radicado. Es independiente de
     * ventanilla_radica_reci_pase_historial y mantiene trazabilidad separada.
     */
    public function up(): void
    {
        Schema::create('ventanilla_radica_reci_compartir_historial', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('radica_reci_id');
            $table->foreign('radica_reci_id', 'compartir_historial_radica_fk')->references('id')->on('ventanilla_radica_reci');

            $table->unsignedBigInteger('usuario_origen_id')->nullable();
            $table->foreign('usuario_origen_id', 'compartir_historial_usuario_orig_fk')->references('id')->on('users');

            $table->unsignedBigInteger('users_cargos_destino_id');
            $table->foreign('users_cargos_destino_id', 'compartir_historial_users_cargos_fk')->references('id')->on('users_cargos');

            $table->unsignedBigInteger('usuario_destino_id');
            $table->foreign('usuario_destino_id', 'compartir_historial_usuario_dest_fk')->references('id')->on('users');

            $table->timestamps();

            $table->index(['radica_reci_id', 'created_at'], 'compartir_historial_radica_fecha_idx');
            $table->index('usuario_destino_id', 'compartir_historial_usuario_dest_idx');
            $table->index('usuario_origen_id', 'compartir_historial_usuario_orig_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_reci_compartir_historial');
    }
};