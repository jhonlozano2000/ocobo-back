<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_archivo_plantillas_documentos', function (Blueprint $table) {
            $table->id();

            // Metadatos del archivo
            $table->string('nombre_original', 255);
            $table->string('nombre_archivo', 255)->comment('UUID aleatorio en disco');
            $table->string('ruta_completa', 512)->comment('Ruta relativa desde storage');
            $table->decimal('peso', 10, 2)->comment('Peso en KB');
            $table->string('extension', 10);
            $table->string('mime_type', 127);
            $table->string('hash_seguridad', 64)->comment('SHA-256 del contenido');
            $table->string('version', 10)->default('1.0');
            $table->text('descripcion')->nullable();

            // Control de estado
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_vencimiento')->nullable();
            $table->unsignedBigInteger('categoria_id')->nullable();

            // Trazabilidad
            $table->unsignedBigInteger('user_crea_id')->nullable();
            $table->unsignedBigInteger('user_actualiza_id')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Índices
            $table->index('activo');
            $table->index('extension');
            $table->index('version');
            $table->index('fecha_vencimiento');
            $table->index(['activo', 'fecha_vencimiento'], 'idx_plantilla_vigente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_plantillas_documentos');
    }
};
