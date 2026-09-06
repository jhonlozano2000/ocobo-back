<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_pqrs_metadata', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->unsignedBigInteger('archivo_id')->nullable()->comment('FK a ventanilla_radica_reci_archivos o archivoadjunto');
            $table->unsignedBigInteger('pqrs_id')->nullable()->comment('FK a ventanilla_pqrs');
            $table->unsignedBigInteger('radicado_id')->nullable()->comment('FK a ventanilla_radica_reci');
            $table->unsignedBigInteger('nivel_clasificacion_id')->nullable();

            // Clasificación de seguridad ISO 27001
            $table->enum('nivel_clasificacion', ['PUBLICO', 'INTERNO', 'CONFIDENCIAL', 'RESERVADO', 'SECRETO'])->default('PUBLICO');
            $table->boolean('propagacion_clasificacion')->default(false);

            // Información del documento
            $table->string('titulo_documento', 500)->nullable();
            $table->text('descripcion')->nullable();
            $table->json('palabras_clave')->nullable();

            // Control de versiones
            $table->string('version_numero', 20)->default('1.0');
            $table->text('version_descripcion')->nullable();
            $table->boolean('es_version_actual')->default(true);

            // Cadena de custodia
            $table->unsignedBigInteger('dueno_documento_id')->nullable();
            $table->unsignedBigInteger('custodio_actual_id')->nullable();

            // Fechas
            $table->timestamp('fecha_creacion_documento')->nullable();
            $table->timestamp('fecha_modificacion')->nullable();
            $table->date('fecha_vencimiento')->nullable();

            // Clasificación documental
            $table->unsignedBigInteger('clasificacion_id')->nullable();
            $table->string('clasificacion_ruta', 500)->nullable();
            $table->string('clasificacion_serie', 255)->nullable();
            $table->string('clasificacion_subserie', 255)->nullable();
            $table->string('clasificacion_tipo_doc', 255)->nullable();

            // Integridad
            $table->string('hash_sha256_archivo', 64)->nullable();
            $table->string('algoritmo_hash', 20)->default('SHA-256');

            // PQRS específicos
            $table->string('tipo_archivo', 20)->default('adjunto');
            $table->string('num_radicado', 50)->nullable();
            $table->string('asunto', 500)->nullable();

            // Estado
            $table->enum('estado_registro', ['ACTIVO', 'ARCHIVADO', 'ELIMINADO'])->default('ACTIVO');

            $table->timestamps();

            // Índices
            $table->index('archivo_id');
            $table->index('pqrs_id');
            $table->index('radicado_id');
            $table->index('num_radicado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_pqrs_metadata');
    }
};
