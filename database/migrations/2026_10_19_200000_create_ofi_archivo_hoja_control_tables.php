<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B19 (diseño de expedientes §3.3): hoja de control y tomos de los expedientes físicos e híbridos
 * [Acuerdo AGN 001/2024 A.4.3.1.8 num. 5].
 *
 * - `ofi_archivo_expediente_tomos`: unidades físicas del expediente (carpetas). Al llenarse una
 *   (D3: 200 folios, configurable) se abre la siguiente con el mismo número de expediente; la
 *   foliación continúa de un tomo al otro.
 * - `ofi_archivo_expediente_hoja_control`: un registro por documento físico, con su tipo
 *   documental y su rango de folios. No se borra ni se renumera: se anula.
 * - `ofi_archivo_expediente_hoja_control_versiones`: la hoja congelada y firmada en cada cierre.
 */
return new class extends Migration
{
    private const TRIGGERS = [
        'ofi_archivo_exp_tomos_inmutable_insert',
        'ofi_archivo_exp_tomos_inmutable_update',
        'ofi_archivo_exp_tomos_inmutable_delete',
        'ofi_archivo_exp_hoja_control_inmutable_insert',
        'ofi_archivo_exp_hoja_control_inmutable_update',
        'ofi_archivo_exp_hoja_control_inmutable_delete',
        'ofi_archivo_exp_hoja_versiones_inmutable_update',
        'ofi_archivo_exp_hoja_versiones_inmutable_delete',
    ];

    public function up(): void
    {
        Schema::create('ofi_archivo_expediente_tomos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes')->restrictOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->unsignedInteger('folio_inicial');
            $table->string('ubicacion_fisica', 200)->nullable();
            $table->string('caja', 50)->nullable();
            $table->string('carpeta', 50)->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['expediente_id', 'numero'], 'uk_expediente_tomo_numero');
        });

        Schema::create('ofi_archivo_expediente_hoja_control', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes')->restrictOnDelete();
            $table->foreignId('tomo_id')->constrained('ofi_archivo_expediente_tomos')->restrictOnDelete();
            $table->unsignedInteger('orden');
            $table->unsignedBigInteger('tipo_documental_id')->nullable()->comment('Nodo TipoDocumento de la TRD');
            $table->string('tipo_documental', 255);
            $table->date('fecha_documento')->nullable();
            $table->unsignedInteger('folio_inicial');
            $table->unsignedInteger('folio_final');
            $table->string('observaciones', 500)->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['expediente_id', 'orden'], 'uk_expediente_hoja_orden');
        });

        Schema::create('ofi_archivo_expediente_hoja_control_versiones', function (Blueprint $table) {
            $table->id();
            // Nombres explícitos: los generados superan los 64 caracteres de MySQL
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes', 'id', 'fk_hoja_versiones_expediente')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('motivo', 40)->default('cierre');
            $table->unsignedInteger('total_documentos');
            $table->unsignedInteger('total_folios');
            $table->unsignedSmallInteger('total_tomos');
            $table->char('hash_contenido', 64)->comment('SHA-256 de los registros y tomos firmados');
            $table->binary('pdf');
            $table->char('hash_pdf', 64);
            $table->foreignId('indice_version_id')->nullable()
                ->constrained('ofi_archivo_expediente_indice_versiones', 'id', 'fk_hoja_versiones_indice')->restrictOnDelete()
                ->comment('Referencia cruzada con el índice electrónico del mismo cierre (híbridos)');
            $table->string('firma_tipo', 30)->default('electronica_interna');
            $table->unsignedBigInteger('firmado_por');
            $table->string('firmante', 255);
            $table->dateTime('firmado_en');
            $table->char('firma', 64)->comment('HMAC-SHA256 de la versión con la llave de la aplicación');
            $table->string('codigo_verificacion', 24)->unique('uk_expediente_hoja_codigo');
            $table->timestamp('created_at')->nullable();

            $table->unique(['expediente_id', 'version'], 'uk_expediente_hoja_version');
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE ofi_archivo_expediente_hoja_control_versiones MODIFY pdf LONGBLOB NOT NULL');

        $estado = fn (string $id) => "(SELECT estado FROM ofi_archivo_expedientes WHERE id = {$id})";
        $error = fn (string $mensaje) => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}';";
        $cambia = fn (array $columnas) => implode(' OR ', array_map(fn ($c) => "NOT (OLD.`{$c}` <=> NEW.`{$c}`)", $columnas));

        // La ubicación del tomo sigue cambiando con la transferencia; su numeración no
        $this->trigger('ofi_archivo_exp_tomos_inmutable_insert', 'BEFORE INSERT ON ofi_archivo_expediente_tomos', "
            IF {$estado('NEW.expediente_id')} <> 'abierto' THEN
                {$error('El expediente no está abierto: no se le pueden abrir tomos.')}
            END IF;
        ");
        $this->trigger('ofi_archivo_exp_tomos_inmutable_update', 'BEFORE UPDATE ON ofi_archivo_expediente_tomos', "
            IF {$cambia(['expediente_id', 'numero', 'folio_inicial'])} THEN
                {$error('El número y el folio inicial del tomo no pueden modificarse.')}
            END IF;
        ");
        $this->trigger('ofi_archivo_exp_tomos_inmutable_delete', 'BEFORE DELETE ON ofi_archivo_expediente_tomos', "
            {$error('Los tomos del expediente no pueden eliminarse.')}
        ");

        $this->trigger('ofi_archivo_exp_hoja_control_inmutable_insert', 'BEFORE INSERT ON ofi_archivo_expediente_hoja_control', "
            IF {$estado('NEW.expediente_id')} <> 'abierto' THEN
                {$error('El expediente no está abierto: su hoja de control no admite documentos nuevos.')}
            END IF;
        ");
        $this->trigger('ofi_archivo_exp_hoja_control_inmutable_update', 'BEFORE UPDATE ON ofi_archivo_expediente_hoja_control', "
            IF {$cambia(['expediente_id', 'tomo_id', 'orden', 'tipo_documental_id', 'tipo_documental', 'fecha_documento', 'folio_inicial', 'folio_final'])} THEN
                {$error('Los registros de la hoja de control no pueden modificarse; se anulan.')}
            END IF;
            IF {$estado('OLD.expediente_id')} <> 'abierto' THEN
                {$error('El expediente no está abierto: su hoja de control no puede modificarse.')}
            END IF;
        ");
        $this->trigger('ofi_archivo_exp_hoja_control_inmutable_delete', 'BEFORE DELETE ON ofi_archivo_expediente_hoja_control', "
            {$error('Los registros de la hoja de control no pueden eliminarse; se anulan.')}
        ");

        $this->trigger('ofi_archivo_exp_hoja_versiones_inmutable_update', 'BEFORE UPDATE ON ofi_archivo_expediente_hoja_control_versiones', "
            {$error('Las versiones firmadas de la hoja de control son inmutables y no pueden modificarse.')}
        ");
        $this->trigger('ofi_archivo_exp_hoja_versiones_inmutable_delete', 'BEFORE DELETE ON ofi_archivo_expediente_hoja_control_versiones', "
            {$error('Las versiones firmadas de la hoja de control son inmutables y no pueden eliminarse.')}
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (self::TRIGGERS as $nombre) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$nombre}");
            }
        }

        Schema::dropIfExists('ofi_archivo_expediente_hoja_control_versiones');
        Schema::dropIfExists('ofi_archivo_expediente_hoja_control');
        Schema::dropIfExists('ofi_archivo_expediente_tomos');
    }

    private function trigger(string $nombre, string $evento, string $cuerpo): void
    {
        DB::unprepared("CREATE TRIGGER {$nombre} {$evento} FOR EACH ROW BEGIN {$cuerpo} END");
    }
};
