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
        Schema::create('ventanilla_radica_reci', function (Blueprint $table) {
            $table->id();

            $table->string('num_radicado', 50)->unique()->comment('Número único del radicado');

            $table->unsignedBigInteger('clasifica_documen_id')->comment('Clasificacion documetnal');
            $table->foreign('clasifica_documen_id')->references('id')->on('clasificacion_documental_trd');

            $table->unsignedBigInteger('tercero_id');
            $table->foreign('tercero_id')->references('id')->on('gestion_terceros');

            $table->unsignedBigInteger('medio_recep_id')->comment('Medio de recepcion del documento');
            $table->foreign('medio_recep_id')->references('id')->on('config_listas_detalles');

            $table->unsignedBigInteger('config_server_id')->nullable()->comment('Rutal de almacenamiento de los archivos digitalizados');
            $table->foreign('config_server_id')->references('id')->on('config_server_archivos');

            $table->unsignedBigInteger('uploaded_by')->nullable()->comment('Usuario que subió el archivo');
            $table->foreign('uploaded_by')->references('id')->on('users');

            $table->date('fec_venci')->nullable()->comment('Fecha de vencimeinto del radicado');
            $table->integer('num_folios')->default(0)->comment('Numero de folios del radicado');
            $table->integer('num_anexos')->default(0)->comment('Numero de anexos del radicado');
            $table->string('descrip_anexos', 300)->nullable()->comment('Descripcion de los anexos');
            $table->string('asunto', 300)->nullable();
            $table->string('archivo_radica', 100)->nullable()->comment('Archivo digitalizado');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_reci');
    }
};
