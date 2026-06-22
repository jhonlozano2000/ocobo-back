<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para tabla de eliminaciones documentales.
 *
 * Registra la eliminación definitiva de expedientes según disposición
 * final de la TRD. Requiere acta digital y firma de al menos 2 funcionarios.
 * Requerido por Acuerdo AGN 004/2019.
 *
 * @see OfiArchivoEliminacion Modelo
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_archivo_eliminaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('expediente_id')
                ->comment('Expediente a eliminar')
                ->constrained('ofi_archivo_expedientes');

            $table->string('acta_eliminacion_path', 500)->nullable()
                ->comment('Ruta del acta de eliminación en PDF');

            $table->dateTime('fecha')
                ->comment('Fecha de la eliminación');

            $table->json('responsable_ids')
                ->comment('IDs de funcionarios que firman el acta');

            $table->enum('metodo', ['destruccion_fisica', 'borrado_seguro'])
                ->comment('Método de eliminación');

            $table->text('testigos')->nullable()
                ->comment('Nombres de testigos');

            $table->foreignId('aprobado_por_id')
                ->comment('Quien aprueba la eliminación')
                ->constrained('users');

            $table->foreignId('usuario_registro_id')
                ->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index('fecha', 'idx_elimin_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_eliminaciones');
    }
};
