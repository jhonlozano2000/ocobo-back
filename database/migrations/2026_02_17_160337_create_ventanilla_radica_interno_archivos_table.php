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
        Schema::create('ventanilla_radica_interno_archivos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('radica_interno_id');
            $table->foreign('radica_interno_id')->references('id')->on('ventanilla_radica_internos')->onDelete('cascade');

            $table->unsignedBigInteger('subido_por')->nullable();
            $table->foreign('subido_por')->references('id')->on('users');

            $table->string('nombre_archivo', 100);
            $table->string('ruta_archivo', 255);
            $table->string('tipo_archivo', 50);
            $table->string('tamano_archivo', 50);
            $table->string('extension_archivo', 10);
            $table->string('nom_origi', 255)->nullable()->comment('Nombre original del archivo');
            $table->unsignedBigInteger('archivo_peso')->nullable()->comment('Peso en bytes del archivo');
            $table->string('hash_sha256', 64)->nullable()->comment('Hash SHA-256 del archivo para integridad ISO 27001');
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
        Schema::dropIfExists('ventanilla_radica_interno_archivos');
    }
};
