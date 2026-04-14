<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radica_enviados_metadata', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->unsignedBigInteger('archivo_id')->nullable();
            $table->unsignedBigInteger('radicado_id')->nullable();
            $table->unsignedBigInteger('nivel_clasificacion_id')->nullable();

            // Clasificación de seguridad ISO 27001
            $table->enum('nivel_clasificacion', ['PUBLICO', 'INTERNO', 'CONFIDENCIAL', 'RESERVADO', 'SECRETO'])->default('PUBLICO');
            $table->boolean('propagacion_clasificacion')->default(false);

            // Información del documento
            $table->string('titulo_documento', 500)->nullable();
            $table->text('descripcion')->nullable();
            $table->json('palabras_clave')->nullable();

            // Control de versiones ISO
            $table->string('version_numero', 20)->default('1.0');
            $table->text('version_descripcion')->nullable();
            $table->boolean('es_version_actual')->default(true);

            // Cadena de custodia
            $table->unsignedBigInteger('dueno_documento_id')->nullable();
            $table->unsignedBigInteger('custodio_actual_id')->nullable();
            $table->unsignedBigInteger('responsable_clasificacion_id')->nullable();

            // Fechas ISO
            $table->timestamp('fecha_creacion_documento')->nullable();
            $table->timestamp('fecha_radicacion')->nullable();
            $table->timestamp('fecha_modificacion')->nullable();
            $table->timestamp('fecha_ultima_consulta')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->date('fecha_disposicion_final')->nullable();
            $table->date('fecha_retencion_fin')->nullable();

            // Clasificación documental
            $table->unsignedBigInteger('clasificacion_id')->nullable();
            $table->string('clasificacion_ruta', 500)->nullable();
            $table->string('clasificacion_serie', 255)->nullable();
            $table->string('clasificacion_subserie', 255)->nullable();
            $table->string('clasificacion_tipo_doc', 255)->nullable();

            // Integridad (ISO 27001 A.8)
            $table->string('hash_sha256_original', 64)->nullable();
            $table->string('hash_sha256_archivo', 64)->nullable();
            $table->string('firma_digital_hash', 64)->nullable();
            $table->string('algoritmo_hash', 20)->default('SHA-256');

            // Control de acceso ISO 27001 A.9
            $table->integer('control_acceso_nivel')->default(1);
            $table->json('roles_autorizados')->nullable();
            $table->json('usuarios_con_acceso')->nullable();
            $table->boolean('requiere_autenticacion_adicional')->default(false);

            // Términos y condiciones
            $table->unsignedBigInteger('terminos_retention_id')->nullable();
            $table->string('categoria_informacion', 100)->nullable();

            // Trazabilidad ISO 27001 A.12.4
            $table->boolean('es_registro_vital')->default(false);
            $table->json('copias_backups')->nullable();

            // Metadatos del radicado
            $table->string('num_radicado', 50)->nullable();
            $table->string('asunto', 500)->nullable();
            $table->string('tercero_nombre', 255)->nullable();
            $table->string('tercero_identificacion', 50)->nullable();
            $table->string('empresa_nombre', 255)->nullable();
            $table->string('sede_nombre', 255)->nullable();
            $table->string('departamento_origen', 255)->nullable();
            $table->string('medio_recepcion', 100)->nullable();
            $table->string('tipo_archivo', 20)->default('adjunto');

            // Estados
            $table->enum('estado_registro', ['ACTIVO', 'ARCHIVADO', 'ELIMINADO', 'DISPOSICION_FINAL'])->default('ACTIVO');
            $table->text('motivo_estado')->nullable();

            $table->timestamps();

            // Índices
            $table->index('archivo_id');
            $table->index('radicado_id');
            $table->index('nivel_clasificacion');
            $table->index('num_radicado');
            $table->index('estado_registro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_enviados_metadata');
    }
};
