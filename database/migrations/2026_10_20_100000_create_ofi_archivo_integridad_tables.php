<?php

use App\Services\OfiArchivo\ConfiguracionArchivo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B21 (diseño de expedientes §8): verificación periódica de la integridad de los binarios. Cada
 * corrida recalcula la huella SHA-256 de los folios elegidos (una muestra semanal, un expediente o
 * los de un lote antes de transferirlo); cada folio revisado queda registrado y los que no
 * coinciden, faltan o no tienen huella abren un incidente que el archivo resuelve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_archivo_integridad_verificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('alcance', 15)->comment('muestra, expediente o transferencia');
            $table->foreignId('expediente_id')->nullable()->constrained('ofi_archivo_expedientes');
            $table->unsignedTinyInteger('porcentaje')->nullable()->comment('Porcentaje de la muestra');
            $table->unsignedInteger('revisados')->default(0);
            $table->unsignedInteger('incidentes')->default(0)->comment('Folios alterados, faltantes o sin huella');
            $table->foreignId('user_id')->nullable()->constrained('users')->comment('Quien la pidió; null = programada');
            $table->dateTime('iniciada_at');
            $table->dateTime('terminada_at')->nullable();

            $table->index('iniciada_at');
        });

        Schema::create('ofi_archivo_integridad_revisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verificacion_id')->constrained('ofi_archivo_integridad_verificaciones')->cascadeOnDelete();
            $table->foreignId('documento_id')->constrained('ofi_archivo_expedientes_documentos');
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes');
            $table->string('resultado', 12)->comment('integro, alterado, faltante, sin_huella o sin_archivo');
            $table->char('hash_esperado', 64)->nullable();
            $table->char('hash_calculado', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // La muestra elige primero los folios con la revisión más antigua
            $table->index(['documento_id', 'created_at']);
        });

        Schema::create('ofi_archivo_integridad_incidentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('ofi_archivo_expedientes_documentos');
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes');
            $table->foreignId('revision_id')->constrained('ofi_archivo_integridad_revisiones');
            $table->string('tipo', 12)->comment('alterado, faltante o sin_huella');
            $table->string('estado', 10)->default('abierto')->comment('abierto o resuelto');
            $table->dateTime('detectado_at');
            $table->dateTime('resuelto_at')->nullable();
            $table->foreignId('resuelto_por_id')->nullable()->constrained('users');
            $table->string('resolucion', 1000)->nullable();
            $table->string('resultado_al_resolver', 12)->nullable()->comment('Resultado de verificar el folio al resolver');

            $table->index(['estado', 'detectado_at']);
            $table->index(['documento_id', 'estado']);
        });

        if (! DB::table('config_varias')->where('clave', ConfiguracionArchivo::INTEGRIDAD_MUESTRA)->exists()) {
            DB::table('config_varias')->insert([
                'clave' => ConfiguracionArchivo::INTEGRIDAD_MUESTRA,
                'valor' => (string) min(100, max(1, (int) config('archivo.integridad_porcentaje_muestra', 5))),
                'descripcion' => 'Porcentaje de folios de expedientes cuya huella se verifica cada semana',
                'tipo' => 'integer',
                'estado' => true,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('config_varias')->where('clave', ConfiguracionArchivo::INTEGRIDAD_MUESTRA)->delete();
        Schema::dropIfExists('ofi_archivo_integridad_incidentes');
        Schema::dropIfExists('ofi_archivo_integridad_revisiones');
        Schema::dropIfExists('ofi_archivo_integridad_verificaciones');
    }
};
