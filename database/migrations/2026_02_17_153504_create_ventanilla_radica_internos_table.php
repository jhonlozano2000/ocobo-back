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
        Schema::create('ventanilla_radica_internos', function (Blueprint $table) {
            $table->id();
            $table->string('num_radicado', 50)->unique()->comment('Número único del radicado');

            $table->unsignedBigInteger('clasifica_documen_id')->comment('Clasificacion documetnal');
            $table->foreign('clasifica_documen_id')->references('id')->on('clasificacion_documental_trd');

            $table->unsignedBigInteger('usuario_crea')->nullable()->comment('Usuario que creó el radicado');
            $table->foreign('usuario_crea')->references('id')->on('users');

            $table->date('fec_docu')->nullable()->comment('Fecha del documento del radicado');
            $table->date('fec_venci')->nullable()->comment('Fecha de vencimiento del radicado');
            $table->integer('num_folios')->default(0)->comment('Numero de folios del radicado');
            $table->integer('num_anexos')->default(0)->comment('Numero de anexos del radicado');
            $table->string('descrip_anexos', 300)->nullable()->comment('Descripcion de los anexos');
            $table->string('asunto', 300)->nullable();
            $table->string('archivo_digital', 100)->nullable()->comment('Nombre del archivo digitalizado');
            $table->string('archivo_tipo', 100)->nullable()->comment('MIME type del archivo');
            $table->unsignedBigInteger('archivo_peso')->nullable()->comment('Peso en bytes del archivo');
            $table->string('hash_sha256', 64)->nullable()->comment('Hash SHA-256 del archivo para integridad ISO 27001');
            $table->string('nom_origi', 255)->nullable()->comment('Nombre original del archivo');

            $table->unsignedBigInteger('subido_por')->nullable()->comment('Usuario que subió el archivo');
            $table->foreign('subido_por')->references('id')->on('users')->onDelete('set null');

            $table->boolean('impri_rotulo')->default(1)->comment('Estado de la impresion del rotulo');

            $table->string('estado_trabajo', 50)->nullable()->comment('Estado de trabajo: recibido, en_proceso, por_vencer, vencido, finalizado');

            $table->boolean('es_pdf_a')->default(false)->comment('Indica si el documento está en formato PDF/A');
            $table->string('pdf_a_nivel', 10)->nullable()->comment('Nivel PDF/A');
            $table->longText('ocr')->nullable()->comment('Texto extraído por OCR');
            $table->boolean('ocr_aplicado')->default(false)->comment('Indica si se aplicó OCR');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_internos');
    }
};
