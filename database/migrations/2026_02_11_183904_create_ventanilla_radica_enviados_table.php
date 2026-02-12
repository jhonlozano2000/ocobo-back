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
        Schema::create('ventanilla_radica_enviados', function (Blueprint $table) {
            $table->id();
            $table->string('num_radicado', 50)->unique()->comment('Número único del radicado');

            $table->unsignedBigInteger('clasifica_documen_id')->comment('Clasificacion documetnal');
            $table->foreign('clasifica_documen_id')->references('id')->on('clasificacion_documental_trd');

            $table->unsignedBigInteger('usuario_crea')->nullable()->comment('Usuario que creó el radicado');
            $table->foreign('usuario_crea')->references('id')->on('users');

            $table->unsignedBigInteger('tercero_enviado_id');
            $table->foreign('tercero_enviado_id')->references('id')->on('gestion_terceros');

            $table->unsignedBigInteger('medio_enviado_id')->comment('Medio de envio del documento');
            $table->foreign('medio_enviado_id')->references('id')->on('config_listas_detalles');

            $table->unsignedBigInteger('config_server_id')->nullable()->comment('Rutal de almacenamiento de los archivos digitalizados');
            $table->foreign('config_server_id')->references('id')->on('config_server_archivos');

            $table->unsignedBigInteger('tipo_respuesta_id');
            $table->foreign('tipo_respuesta_id')->references('id')->on('config_listas_detalles');

            $table->unsignedBigInteger('subido_por')->nullable()->comment('Usuario que subió el archivo');
            $table->foreign('subido_por')->references('id')->on('users');

            $table->date('fec_docu')->nullable()->comment('Fecha del documento del radicado');
            $table->integer('num_folios')->default(0)->comment('Numero de folios del radicado');
            $table->integer('num_anexos')->default(0)->comment('Numero de anexos del radicado');
            $table->string('descrip_anexos', 300)->nullable()->comment('Descripcion de los anexos');
            $table->string('asunto', 300)->nullable();
            $table->string('radicado_respuesta', 300)->nullable()->comment('Radicado de respuesta');
            $table->string('archivo_digital', 100)->nullable()->comment('Nombre del archivo digitalizado');


            $table->boolean('impri_rotulo')->default(1)->comment('Estado de la impresion del rotulo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_enviados');
    }
};
