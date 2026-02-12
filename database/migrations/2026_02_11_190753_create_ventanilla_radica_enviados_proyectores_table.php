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
        Schema::create('ventanilla_radica_enviados_proyectores', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('radica_enviado_id');
            $table->foreign('radica_enviado_id')->references('id')->on('ventanilla_radica_enviados')->onDelete('cascade');

            $table->unsignedBigInteger('users_cargos_id');
            $table->foreign('users_cargos_id')->references('id')->on('users_cargos');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_enviados_proyectores');
    }
};
