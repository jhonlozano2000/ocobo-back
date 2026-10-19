<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * B11 (diseño de expedientes): la base de datos, y no solo el controlador, impide alterar un
     * expediente que ya no está abierto, sus folios, su índice encadenado y las versiones firmadas
     * del índice [Acuerdo AGN 001/2024 A.4.3.2.4; Acuerdo 003/2015 art. 13].
     *
     * - Expediente no abierto: su identificación, su clasificación, su cierre, su índice y su
     *   retención quedan fijos; no se borra. Solo un expediente cerrado se reabre y, al hacerlo,
     *   puede limpiar los datos del cierre (B13). Estado, fase, ubicación y acceso siguen
     *   cambiando con la transferencia, la disposición y la desclasificación.
     * - Folios: no se incorporan, modifican ni borran si su expediente no está abierto.
     * - Índice: las columnas encadenadas no cambian nunca y las filas no se borran; la anulación
     *   y el tipo documental solo cambian con el expediente abierto.
     * - Versiones firmadas del índice: solo inserción.
     *
     * Requiere el privilegio TRIGGER en MySQL. En otros motores no hace nada.
     */
    private const TRIGGERS = [
        'ofi_archivo_expedientes_inmutable_update',
        'ofi_archivo_expedientes_inmutable_delete',
        'ofi_archivo_expedientes_docs_inmutable_insert',
        'ofi_archivo_expedientes_docs_inmutable_update',
        'ofi_archivo_expedientes_docs_inmutable_delete',
        'ofi_archivo_expediente_indice_inmutable_insert',
        'ofi_archivo_expediente_indice_inmutable_update',
        'ofi_archivo_expediente_indice_inmutable_delete',
        'ofi_archivo_exp_indice_versiones_inmutable_update',
        'ofi_archivo_exp_indice_versiones_inmutable_delete',
    ];

    private const IDENTIDAD = [
        'numero_expediente', 'nombre_expediente', 'anio', 'dependencia_id', 'dependencia_cod', 'dependencia_nom',
        'serie_trd_id', 'subserie_trd_id', 'fecha_apertura', 'usuario_apertura_id', 'deleted_at',
    ];

    private const CIERRE = [
        'fecha_cierre', 'usuario_cierre_id', 'motivo_cierre', 'hash_indice', 'trd_snapshot',
        'fecha_fin_gestion', 'fecha_fin_central', 'total_folios_elec',
    ];

    private const FOLIO = [
        'expediente_id', 'tipo', 'documentable_type', 'documentable_id', 'numero_folio', 'orden',
        'tipo_documental', 'tipo_documental_id', 'fecha_documento', 'asunto', 'autor', 'formato_archivo',
        'tamano_bytes', 'hash_sha256', 'nombre_original', 'detalle', 'archivo_path', 'activo',
        'fecha_incorporacion', 'usuario_id',
    ];

    /** Las de OfiArchivoExpedienteIndice::CAMPOS_ENCADENADOS y las huellas de la cadena. */
    private const ENCADENADAS = [
        'expediente_id', 'documento_id', 'orden', 'numero_folio', 'origen', 'fecha_incorporacion',
        'hash_sha256', 'hash_anterior', 'hash_cadena',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $this->dropTriggers();

        $cambia = fn (array $columnas) => implode(' OR ', array_map(fn ($c) => "NOT (OLD.`{$c}` <=> NEW.`{$c}`)", $columnas));
        $estado = fn (string $id) => "(SELECT estado FROM ofi_archivo_expedientes WHERE id = {$id})";
        $error = fn (string $mensaje) => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}';";

        $this->trigger('ofi_archivo_expedientes_inmutable_update', 'BEFORE UPDATE ON ofi_archivo_expedientes', "
            IF NEW.estado = 'abierto' AND OLD.estado <> 'abierto' AND OLD.estado <> 'cerrado' THEN
                {$error('Solo un expediente cerrado puede reabrirse.')}
            END IF;
            IF OLD.estado <> 'abierto' THEN
                IF {$cambia(self::IDENTIDAD)} THEN
                    {$error('El expediente ya no está abierto: su identificación y su clasificación no pueden modificarse.')}
                END IF;
                IF NEW.estado <> 'abierto' AND ({$cambia(self::CIERRE)}) THEN
                    {$error('El expediente ya no está abierto: su cierre, su índice y su retención no pueden modificarse.')}
                END IF;
            END IF;
        ");
        $this->trigger('ofi_archivo_expedientes_inmutable_delete', 'BEFORE DELETE ON ofi_archivo_expedientes', "
            IF OLD.estado <> 'abierto' THEN
                {$error('El expediente ya no está abierto y no puede eliminarse.')}
            END IF;
        ");

        $this->trigger('ofi_archivo_expedientes_docs_inmutable_insert', 'BEFORE INSERT ON ofi_archivo_expedientes_documentos', "
            IF {$estado('NEW.expediente_id')} <> 'abierto' THEN
                {$error('El expediente no está abierto: no se le pueden incorporar documentos.')}
            END IF;
        ");
        $this->trigger('ofi_archivo_expedientes_docs_inmutable_update', 'BEFORE UPDATE ON ofi_archivo_expedientes_documentos', "
            IF ({$estado('OLD.expediente_id')} <> 'abierto' OR {$estado('NEW.expediente_id')} <> 'abierto')
                AND ({$cambia(self::FOLIO)}) THEN
                {$error('El expediente no está abierto: sus documentos no pueden modificarse.')}
            END IF;
        ");
        $this->trigger('ofi_archivo_expedientes_docs_inmutable_delete', 'BEFORE DELETE ON ofi_archivo_expedientes_documentos', "
            IF {$estado('OLD.expediente_id')} <> 'abierto' THEN
                {$error('El expediente no está abierto: sus documentos no pueden eliminarse.')}
            END IF;
        ");

        $this->trigger('ofi_archivo_expediente_indice_inmutable_insert', 'BEFORE INSERT ON ofi_archivo_expediente_indice', "
            IF {$estado('NEW.expediente_id')} <> 'abierto' THEN
                {$error('El expediente no está abierto: su índice no admite filas nuevas.')}
            END IF;
        ");
        $this->trigger('ofi_archivo_expediente_indice_inmutable_update', 'BEFORE UPDATE ON ofi_archivo_expediente_indice', "
            IF {$cambia(self::ENCADENADAS)} THEN
                {$error('Las columnas encadenadas del índice del expediente no pueden modificarse.')}
            END IF;
            IF {$estado('OLD.expediente_id')} <> 'abierto' THEN
                {$error('El expediente no está abierto: su índice no puede modificarse.')}
            END IF;
        ");
        $this->trigger('ofi_archivo_expediente_indice_inmutable_delete', 'BEFORE DELETE ON ofi_archivo_expediente_indice', "
            {$error('Las filas del índice del expediente no pueden eliminarse; se anulan.')}
        ");

        $this->trigger('ofi_archivo_exp_indice_versiones_inmutable_update', 'BEFORE UPDATE ON ofi_archivo_expediente_indice_versiones', "
            {$error('Las versiones firmadas del índice son inmutables y no pueden modificarse.')}
        ");
        $this->trigger('ofi_archivo_exp_indice_versiones_inmutable_delete', 'BEFORE DELETE ON ofi_archivo_expediente_indice_versiones', "
            {$error('Las versiones firmadas del índice son inmutables y no pueden eliminarse.')}
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->dropTriggers();
        }
    }

    private function trigger(string $nombre, string $evento, string $cuerpo): void
    {
        DB::unprepared("CREATE TRIGGER {$nombre} {$evento} FOR EACH ROW BEGIN {$cuerpo} END");
    }

    private function dropTriggers(): void
    {
        foreach (self::TRIGGERS as $nombre) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$nombre}");
        }
    }
};
