<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ventanilla_radica_reci_responsa', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('radica_reci_id');
            $table->foreign('radica_reci_id')->references('id')->on('ventanilla_radica_reci');

            $table->unsignedBigInteger('users_cargos_id');
            $table->foreign('users_cargos_id')->references('id')->on('users_cargos');

            $table->boolean('custodio')->default(0)->comment('Usuario que va a tener la custodia del documento');
            $table->datetime('fechor_visto')->nullable()->comment('Fecha y hora en el cual el responsable ve el radicado');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_reci_responsa');
    }
};
