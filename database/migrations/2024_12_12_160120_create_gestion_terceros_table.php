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
        Schema::create('gestion_terceros', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('pais_id')->nullable();
            $table->foreign('pais_id')->references('id')->on('config_divi_poli');

            $table->unsignedBigInteger('departamento_id')->nullable();
            $table->foreign('departamento_id')->references('id')->on('config_divi_poli');

            $table->unsignedBigInteger('municipio_id')->nullable();
            $table->foreign('municipio_id')->references('id')->on('config_divi_poli');

            $table->string('num_docu_nit', 25)->nullable();
            $table->string('nom_razo_soci', 150)->nullable();
            $table->string('direccion', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email', 70)->nullable();
            $table->enum('tipo', ['Natural', 'Juridico']);
            $table->boolean('notifica_email')->default(0);
            $table->boolean('notifica_msm')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gestion_terceros');
    }
};
