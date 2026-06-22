<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->id();

            // 1. Identificación y Clasificación (AGN)
            $table->string('numero_expediente', 100)->unique()->comment('Código YYYY-DEP-SER-CONSECUTIVO');
            $table->string('nombre_expediente', 500)->comment('Nombre descriptivo del expediente');
            $table->unsignedSmallInteger('anio')->comment('Año de apertura para partición y optimización ABAC');

            // 2. Relaciones Estructurales (ABAC y TRD)
            $table->foreignId('dependencia_id')->comment('Dependencia productora del expediente')->constrained('calidad_organigrama');
            $table->foreignId('serie_trd_id')->comment('Vínculo obligatorio a la TRD')->constrained('clasificacion_documental_trd');
            $table->unsignedBigInteger('subserie_trd_id')->nullable()->comment('Subserie TRD (si aplica)');

            // 3. Estados y Ciclo de Vida (Vuexy Badge Compatibility)
            $table->enum('estado', ['abierto', 'cerrado', 'transferido', 'eliminado'])->default('abierto');
            $table->enum('soporte', ['electronico', 'fisico', 'mixto'])->default('electronico')->comment('Declaración de medio');
            $table->date('fecha_apertura');
            $table->date('fecha_cierre')->nullable();

            // 4. Ubicación Física (FUID - Acuerdo 042/2002) - Híbridos
            $table->string('ubicacion_fisica', 200)->nullable()->comment('Bodega, estante o depósito unificado');
            $table->string('caja', 50)->nullable();
            $table->string('carpeta', 50)->nullable();
            $table->unsignedMediumInteger('folios_fisicos')->default(0)->comment('Conteo de folios en papel');

            // 5. Notas y Descripciones (1FN)
            $table->text('observaciones_generales')->nullable()->comment('Información adicional o descripción del expediente');

            // 6. Auditoría e Integridad (ISO 27001 y Acuerdo 003/2015)
            $table->unsignedMediumInteger('total_folios_elec')->default(0)->comment('Conteo automático de documentos digitales');
            $table->string('hash_indice', 64)->nullable()->comment('Hash SHA-256 del índice electrónico al cerrar');

            $table->foreignId('usuario_apertura_id')->constrained('users');
            $table->foreignId('usuario_cierre_id')->nullable()->constrained('users');
            $table->string('motivo_cierre', 500)->nullable();

            $table->timestamps();
            $table->softDeletes(); // Para recuperación de desastres (ISO 27001)

            // 7. Índices de Rendimiento (Consultas ABAC y Filtros)
            $table->index(['anio', 'dependencia_id'], 'idx_abac_anio_dep');
            $table->index('estado', 'idx_estado_exp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_expedientes');
    }
};
