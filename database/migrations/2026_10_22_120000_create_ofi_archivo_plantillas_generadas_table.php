<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas generadas con el editor de Mis Documentos (Gestión de Archivo → Plantillas de documentos →
 * Generador de plantillas), a diferencia de las cargadas (ofi_archivo_plantillas_documentos), que son archivos.
 *
 * - Institucionales (user_id del autor, institucional = 1): las publica quien tiene permiso y las ve todo el mundo.
 * - Personales (institucional = 0): las guarda un usuario desde el editor y solo las ve él.
 *
 * El contenido es el JSON de TipTap con nodos «variable» (fecha, ciudad, datos del usuario y de la entidad) que
 * se llenan al crear cada documento. Para editarlas se usa el mismo editor con un documento de trabajo oculto
 * (mi_bandeja_temp_reci_documentos.plantilla_generada_id), que no aparece en Mis Documentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_archivo_plantillas_generadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tipo_id')->nullable()->constrained('config_listas_detalles')->nullOnDelete();
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('institucional')->default(false);
            $table->boolean('activo')->default(true);
            $table->json('contenido');
            $table->json('configuracion')->nullable();
            $table->foreignId('user_actualiza_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institucional', 'activo']);
        });

        Schema::table('mi_bandeja_temp_reci_documentos', function (Blueprint $table) {
            $table->foreignId('plantilla_generada_id')->nullable()->after('radica_reci_id')
                ->constrained('ofi_archivo_plantillas_generadas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mi_bandeja_temp_reci_documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plantilla_generada_id');
        });

        Schema::dropIfExists('ofi_archivo_plantillas_generadas');
    }
};
