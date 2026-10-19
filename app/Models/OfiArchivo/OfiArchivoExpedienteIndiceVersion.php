<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * B10: índice electrónico congelado y firmado al cerrar el expediente. Solo inserción;
 * una reapertura y un nuevo cierre generan otra versión y conservan las anteriores.
 *
 * @property int $expediente_id
 * @property int $version consecutivo dentro del expediente (1, 2, 3...)
 * @property string $xml índice en XML tal como se firmó
 * @property string $pdf índice en PDF tal como se firmó (binario)
 * @property string $firma_tipo electronica_interna | digital_certificado
 * @property string $firma HMAC-SHA256 de la versión (firma electrónica interna)
 * @property string $codigo_verificacion código impreso para verificar la versión
 * @property string|null $sello_tiempo token RFC 3161 en base64
 */
class OfiArchivoExpedienteIndiceVersion extends Model
{
    protected $table = 'ofi_archivo_expediente_indice_versiones';

    public const UPDATED_AT = null;

    protected $fillable = [
        'expediente_id',
        'version',
        'motivo',
        'total_folios',
        'hash_indice',
        'hash_cadena',
        'xml',
        'hash_xml',
        'pdf',
        'hash_pdf',
        'firma_tipo',
        'firmado_por',
        'firmante',
        'firmado_en',
        'firma',
        'codigo_verificacion',
        'sello_tiempo',
        'sello_tiempo_fecha',
    ];

    protected $hidden = ['xml', 'pdf', 'sello_tiempo'];

    protected $casts = [
        'expediente_id' => 'integer',
        'version' => 'integer',
        'total_folios' => 'integer',
        'firmado_por' => 'integer',
        'firmado_en' => 'datetime',
        'sello_tiempo_fecha' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Las versiones firmadas del índice son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Las versiones firmadas del índice son inmutables.'));
    }

    public function expediente()
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }

    public function firmadoPor()
    {
        return $this->belongsTo(User::class, 'firmado_por');
    }
}
