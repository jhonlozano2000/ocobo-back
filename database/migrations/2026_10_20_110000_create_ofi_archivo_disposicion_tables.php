<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B22 (diseño de expedientes §3.8 S y M/D): disposición final desde el archivo central.
 *
 * - La TRD dice cómo se seleccionan los expedientes de una serie de selección: muestra aleatoria
 *   (porcentaje), sistemática (uno de cada N) o cualitativa (la elige el archivo con el criterio
 *   del procedimiento).
 * - Cuando se cumple la retención en central, el expediente pasa a 'en_disposicion' con su
 *   destino: conservar (CT, o S seleccionado) o eliminar (E, o S no seleccionado). El de selección
 *   queda sin destino hasta que se confirma su lote de selección.
 * - La digitalización (M/D) queda registrada con su huella y su certificado, solo inserción: no
 *   reemplaza al original ni autoriza a eliminarlo [Acuerdo 001/2024 A.4.3.2.5 par.].
 */
return new class extends Migration
{
    private const TRIGGERS = [
        'ofi_archivo_digitalizaciones_inmutable_update',
        'ofi_archivo_digitalizaciones_inmutable_delete',
    ];

    public function up(): void
    {
        Schema::table('clasificacion_documental_trd', function (Blueprint $table) {
            $table->enum('metodo_seleccion', ['aleatorio', 'sistematico', 'cualitativo'])->nullable()->after('s');
            $table->unsignedTinyInteger('porcentaje_seleccion')->nullable()->after('metodo_seleccion')->comment('Muestra aleatoria: porcentaje que se conserva');
            $table->unsignedSmallInteger('intervalo_seleccion')->nullable()->after('porcentaje_seleccion')->comment('Muestra sistemática: se conserva uno de cada N');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ofi_archivo_expedientes MODIFY estado ENUM('abierto','cerrado','en_transferencia','en_disposicion','transferido','eliminado') NOT NULL DEFAULT 'abierto'");
        }
        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->dateTime('fecha_disposicion')->nullable()->after('fecha_fin_central')->comment('Cuándo pasó a disposición final');
            $table->enum('destino_disposicion', ['conservar', 'eliminar'])->nullable()->after('fecha_disposicion');
        });

        Schema::create('ofi_archivo_seleccion_lotes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->foreignId('trd_id')->constrained('clasificacion_documental_trd')->comment('Serie o subserie de selección');
            $table->string('trd_cod', 50);
            $table->string('trd_nom');
            $table->string('metodo', 12)->comment('aleatorio, sistematico o cualitativo');
            $table->unsignedTinyInteger('porcentaje')->nullable();
            $table->unsignedSmallInteger('intervalo')->nullable();
            $table->unsignedInteger('semilla')->nullable()->comment('Reproduce la muestra aleatoria');
            $table->string('estado', 12)->default('propuesta')->comment('propuesta, confirmada o anulada');
            $table->string('justificacion', 2000)->nullable()->comment('Obligatoria si se aparta de la propuesta o es cualitativa');
            $table->foreignId('user_id')->constrained('users')->comment('Quien la propuso');
            $table->foreignId('confirmado_por_id')->nullable()->constrained('users');
            $table->dateTime('confirmado_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'trd_id']);
        });

        Schema::create('ofi_archivo_seleccion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('ofi_archivo_seleccion_lotes')->cascadeOnDelete();
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes');
            $table->unsignedInteger('orden')->comment('Posición en la población, por fecha de cierre');
            $table->boolean('propuesto')->default(false)->comment('Lo eligió el método');
            $table->boolean('seleccionado')->default(false)->comment('Decisión final: se conserva');

            $table->unique(['lote_id', 'expediente_id']);
            $table->index('expediente_id');
        });

        Schema::create('ofi_archivo_digitalizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes');
            $table->string('archivo_path', 500);
            $table->string('nombre_original');
            $table->string('formato', 100);
            $table->unsignedBigInteger('tamano_bytes');
            $table->char('hash_sha256', 64);
            $table->unsignedInteger('paginas')->nullable();
            $table->unsignedSmallInteger('resolucion_dpi')->nullable();
            $table->string('observaciones', 1000)->nullable();
            $table->binary('certificado');
            $table->char('hash_certificado', 64);
            $table->string('codigo_verificacion', 30)->unique();
            $table->char('firma', 64);
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('digitalizado_at');

            $table->index('expediente_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ofi_archivo_digitalizaciones MODIFY certificado LONGBLOB NOT NULL');
            foreach (['UPDATE' => self::TRIGGERS[0], 'DELETE' => self::TRIGGERS[1]] as $evento => $nombre) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$nombre}");
                DB::unprepared("CREATE TRIGGER {$nombre} BEFORE {$evento} ON ofi_archivo_digitalizaciones FOR EACH ROW BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El registro de digitalización es inmutable.';
                END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (self::TRIGGERS as $nombre) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$nombre}");
            }
        }
        Schema::dropIfExists('ofi_archivo_digitalizaciones');
        Schema::dropIfExists('ofi_archivo_seleccion_items');
        Schema::dropIfExists('ofi_archivo_seleccion_lotes');

        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->dropColumn(['fecha_disposicion', 'destino_disposicion']);
        });
        if (DB::getDriverName() === 'mysql') {
            // Los que están en disposición vuelven a cerrados en el central antes de quitar el estado
            DB::table('ofi_archivo_expedientes')->where('estado', 'en_disposicion')->update(['estado' => 'cerrado']);
            DB::statement("ALTER TABLE ofi_archivo_expedientes MODIFY estado ENUM('abierto','cerrado','en_transferencia','transferido','eliminado') NOT NULL DEFAULT 'abierto'");
        }

        Schema::table('clasificacion_documental_trd', function (Blueprint $table) {
            $table->dropColumn(['metodo_seleccion', 'porcentaje_seleccion', 'intervalo_seleccion']);
        });
    }
};
