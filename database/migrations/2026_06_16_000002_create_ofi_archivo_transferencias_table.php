<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para tabla de transferencias documentales.
 *
 * Registra transferencias primarias (archivo gestión → archivo central)
 * y secundarias (archivo central → archivo histórico/baja).
 * Requerido por Acuerdo AGN 004/2019.
 *
 * @see OfiArchivoTransferencia Modelo
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_archivo_transferencias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('expediente_id')
                ->comment('Expediente transferido')
                ->constrained('ofi_archivo_expedientes');

            $table->enum('tipo', ['primaria', 'secundaria'])
                ->comment('primaria: gestión→central, secundaria: central→histórico');

            $table->string('origen', 200)
                ->comment('Dependencia/archivo de origen');

            $table->string('destino', 200)
                ->comment('Dependencia/archivo de destino');

            $table->foreignId('responsable_origen_id')
                ->comment('Responsable en origen')
                ->constrained('users');

            $table->foreignId('responsable_destino_id')->nullable()
                ->comment('Responsable en destino')
                ->constrained('users');

            $table->dateTime('fecha_transferencia')
                ->comment('Fecha de la transferencia');

            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada', 'completada'])
                ->default('pendiente');

            $table->string('fuid_path', 500)->nullable()
                ->comment('Ruta del FUID generado');

            $table->text('observaciones')->nullable();

            $table->foreignId('usuario_registro_id')
                ->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index('tipo', 'idx_transf_tipo');
            $table->index('estado', 'idx_transf_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_transferencias');
    }
};
