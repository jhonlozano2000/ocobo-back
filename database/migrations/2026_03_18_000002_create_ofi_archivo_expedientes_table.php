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
        Schema::create('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->id();


            // Identificación única según AGN
            $table->string('numero_expediente', 100)->unique()->comment('Código YYYY-DEP-SER-CONSECUTIVO');
            $table->string('nombre_expediente', 300)->comment('Nombre descriptivo del expediente');

            // Relaciones estructurales
            $table->unsignedBigInteger('dependencia_id')->comment('Dependencia productora del expediente');
            $table->foreign('dependencia_id')->references('id')->on('calidad_organigrama');

            $table->unsignedBigInteger('serie_trd_id')->comment('Vínculo obligatorio a la TRD para tiempos de retención');
            $table->foreign('serie_trd_id')->references('id')->on('clasificacion_documental_trd');

            // Estados y Fechas
            $table->enum('estado', ['Abierto', 'Cerrado', 'Transferido'])->default('Abierto');
            $table->timestamp('fecha_apertura')->useCurrent();
            $table->timestamp('fecha_cierre')->nullable();

            // Ubicación Física (FUID - Acuerdo 042/2002)
            $table->string('deposito', 100)->nullable()->comment('Bodega o estante físico');
            $table->string('caja', 50)->nullable()->comment('Número de caja física');
            $table->string('carpeta', 50)->nullable()->comment('Número de carpeta física');
            $table->integer('folios_fisicos')->default(0)->comment('Conteo de folios en papel (Híbrido)');

            // Información adicional solicitada por el usuario
            $table->text('observacion_1')->nullable();
            $table->text('observacion_2')->nullable();
            $table->text('observacion_3')->nullable();

            // Auditoría e Integridad (ISO 27001)
            $table->unsignedBigInteger('usuario_apertura_id');
            $table->foreign('usuario_apertura_id')->references('id')->on('users');


            $table->integer('total_folios_elec')->default(0)->comment('Conteo automático de documentos digitales');
            $table->string('hash_indice', 64)->nullable()->comment('Hash SHA-256 del índice electrónico al cerrar');

            $table->timestamps();
            $table->softDeletes(); // Para recuperación de desastres (ISO 27001)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_expedientes');
    }
};
