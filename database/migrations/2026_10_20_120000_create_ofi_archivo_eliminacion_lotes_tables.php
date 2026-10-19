<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B18 (diseño de expedientes §3.8 E): eliminación documental por lote [Acuerdo 001/2024].
 *
 * - La versión de la TRD registra su inscripción en el RUSD; sin ella no se propone eliminar.
 * - Un lote por serie o subserie completa, con su inventario publicado 60 días hábiles para que
 *   la ciudadanía haga observaciones. Si las hay, el comité emite concepto en 30 días hábiles.
 * - El Comité Institucional aprueba (número y fecha del acta), el presidente y el secretario
 *   técnico firman y el archivo ejecuta: borra los binarios propios del expediente y deja su
 *   metadata, índice, inventario y acta, que siguen publicados.
 * - El lote ejecutado no se modifica ni se borra.
 */
return new class extends Migration
{
    private const TRIGGERS = [
        'ofi_archivo_eliminacion_lotes_inmutable_update',
        'ofi_archivo_eliminacion_lotes_inmutable_delete',
    ];

    public function up(): void
    {
        Schema::table('clasificacion_documental_trd_versiones', function (Blueprint $table) {
            $table->boolean('rusd_inscrita')->default(false)->after('fecha_aprobacion')->comment('Inscrita en el Registro Único de Series Documentales');
            $table->string('rusd_numero', 50)->nullable()->after('rusd_inscrita');
            $table->date('rusd_fecha')->nullable()->after('rusd_numero');
        });

        Schema::create('ofi_archivo_eliminacion_lotes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->foreignId('trd_id')->constrained('clasificacion_documental_trd')->comment('Serie o subserie que se elimina');
            $table->string('trd_cod', 50);
            $table->string('trd_nom');
            $table->string('dependencia_cod', 50)->nullable();
            $table->string('dependencia_nom')->nullable();
            $table->foreignId('trd_version_id')->nullable()->constrained('clasificacion_documental_trd_versiones');
            $table->string('rusd_numero', 50)->nullable()->comment('Inscripción RUSD vigente al proponer');
            $table->binary('inventario');
            $table->char('hash_inventario', 64);
            $table->dateTime('fecha_publicacion');
            $table->date('fin_publicacion')->comment('60 días hábiles desde la publicación');
            $table->date('fin_concepto')->nullable()->comment('30 días hábiles tras la publicación, si hubo observaciones');
            $table->string('decision_concepto', 30)->nullable()->comment('continuar, suspender, ampliar_retencion o cambiar_disposicion');
            $table->text('concepto')->nullable();
            $table->foreignId('concepto_por_id')->nullable()->constrained('users');
            $table->dateTime('concepto_at')->nullable();
            $table->string('acta_comite_numero', 50)->nullable();
            $table->date('acta_comite_fecha')->nullable();
            $table->string('metodo', 20)->nullable()->comment('destruccion_fisica, borrado_seguro o mixto');
            $table->foreignId('presidente_id')->nullable()->constrained('users');
            $table->foreignId('secretario_id')->nullable()->constrained('users');
            $table->foreignId('comite_por_id')->nullable()->constrained('users');
            $table->dateTime('comite_at')->nullable();
            $table->char('firma_presidente', 64)->nullable();
            $table->dateTime('firmado_presidente_at')->nullable();
            $table->char('firma_secretario', 64)->nullable();
            $table->dateTime('firmado_secretario_at')->nullable();
            $table->binary('acta')->nullable();
            $table->char('hash_acta', 64)->nullable();
            $table->string('codigo_verificacion', 30)->nullable()->unique();
            $table->foreignId('ejecutado_por_id')->nullable()->constrained('users');
            $table->dateTime('ejecutado_at')->nullable();
            $table->string('motivo_anulacion', 1000)->nullable();
            $table->string('estado', 20)->default('publicado')
                ->comment('publicado, en_concepto, por_aprobar, aprobado, por_ejecutar, ejecutado, suspendido o anulado');
            $table->foreignId('user_id')->constrained('users')->comment('Quien lo propuso; no puede ejecutarlo');
            $table->timestamps();

            $table->index(['estado', 'fin_publicacion']);
        });

        Schema::create('ofi_archivo_eliminacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('ofi_archivo_eliminacion_lotes')->cascadeOnDelete();
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes');
            $table->unsignedInteger('orden');
            $table->string('numero_expediente', 100);
            $table->string('nombre_publico', 500)->comment('El de clasificados y reservados no se publica');
            $table->date('fecha_inicial')->nullable();
            $table->date('fecha_final')->nullable();
            $table->unsignedInteger('folios')->default(0);
            $table->string('soporte', 30)->nullable();
            $table->unsignedInteger('archivos_borrados')->nullable();
            $table->unsignedInteger('archivos_compartidos')->nullable()->comment('Radicados que siguen en otro expediente: no se borran');

            $table->unique(['lote_id', 'expediente_id']);
            $table->index('expediente_id');
        });

        Schema::create('ofi_archivo_eliminacion_observaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('ofi_archivo_eliminacion_lotes')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('ofi_archivo_eliminacion_items')->nullOnDelete();
            $table->string('nombre', 150);
            $table->string('identificacion', 30)->nullable();
            $table->string('correo', 150)->nullable();
            $table->text('observacion');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('lote_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ofi_archivo_eliminacion_lotes MODIFY inventario LONGBLOB NOT NULL, MODIFY acta LONGBLOB NULL');
            foreach (['UPDATE' => self::TRIGGERS[0], 'DELETE' => self::TRIGGERS[1]] as $evento => $nombre) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$nombre}");
                DB::unprepared("CREATE TRIGGER {$nombre} BEFORE {$evento} ON ofi_archivo_eliminacion_lotes FOR EACH ROW BEGIN
                    IF OLD.estado = 'ejecutado' THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La eliminación ya se ejecutó: su lote y su acta son inmutables.';
                    END IF;
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
        Schema::dropIfExists('ofi_archivo_eliminacion_observaciones');
        Schema::dropIfExists('ofi_archivo_eliminacion_items');
        Schema::dropIfExists('ofi_archivo_eliminacion_lotes');

        Schema::table('clasificacion_documental_trd_versiones', function (Blueprint $table) {
            $table->dropColumn(['rusd_inscrita', 'rusd_numero', 'rusd_fecha']);
        });
    }
};
