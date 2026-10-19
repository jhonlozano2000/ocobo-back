<?php

namespace App\Models\OfiArchivo;

use Illuminate\Database\Eloquent\Model;

/**
 * B19: hoja de control congelada y firmada al cerrar el expediente. Solo inserción; una
 * reapertura y un nuevo cierre generan otra versión.
 *
 * @property int $expediente_id
 * @property int $version
 * @property string $pdf hoja en PDF tal como se firmó (binario)
 * @property string $firma HMAC-SHA256 de la versión (firma electrónica interna)
 * @property string $codigo_verificacion
 * @property int|null $indice_version_id índice electrónico firmado en el mismo cierre
 */
class OfiArchivoExpedienteHojaControlVersion extends Model
{
    protected $table = 'ofi_archivo_expediente_hoja_control_versiones';

    public const UPDATED_AT = null;

    protected $fillable = [
        'expediente_id',
        'version',
        'motivo',
        'total_documentos',
        'total_folios',
        'total_tomos',
        'hash_contenido',
        'pdf',
        'hash_pdf',
        'indice_version_id',
        'firma_tipo',
        'firmado_por',
        'firmante',
        'firmado_en',
        'firma',
        'codigo_verificacion',
    ];

    protected $hidden = ['pdf', 'firma'];

    protected $casts = [
        'expediente_id' => 'integer',
        'version' => 'integer',
        'total_documentos' => 'integer',
        'total_folios' => 'integer',
        'total_tomos' => 'integer',
        'indice_version_id' => 'integer',
        'firmado_por' => 'integer',
        'firmado_en' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Las versiones firmadas de la hoja de control son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Las versiones firmadas de la hoja de control no pueden eliminarse.'));
    }

    public function indiceVersion()
    {
        return $this->belongsTo(OfiArchivoExpedienteIndiceVersion::class, 'indice_version_id');
    }
}
