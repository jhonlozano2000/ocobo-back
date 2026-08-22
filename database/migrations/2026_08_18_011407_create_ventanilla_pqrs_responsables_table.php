<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla de responsables para PQRS - equivalente a ventanilla_radica_reci_responsa
     */
    public function up(): void
    {
        Schema::create('ventanilla_pqrs_responsables', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('pqrs_id');
            $table->foreign('pqrs_id', 'pqrs_resp_pqrs_fk')->references('id')->on('ventanilla_pqrs');

            $table->unsignedBigInteger('users_cargos_id');
            $table->foreign('users_cargos_id', 'pqrs_resp_users_cargos_fk')->references('id')->on('users_cargos');

            $table->boolean('custodio')->default(0)->comment('Usuario que va a tener la custodia del documento');
            $table->datetime('fechor_visto')->nullable()->comment('Fecha y hora en el cual el responsable ve el PQRS');

            $table->timestamps();

            $table->index(['pqrs_id'], 'pqrs_resp_pqrs_idx');
            $table->index(['users_cargos_id'], 'pqrs_resp_users_cargos_idx');
            $table->index(['pqrs_id', 'custodio'], 'pqrs_resp_pqrs_custodio_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_pqrs_responsables');
    }
};
