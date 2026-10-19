<?php

namespace App\Models\OfiArchivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * B10: fila del índice electrónico de un expediente, escrita al incorporar el folio.
 *
 * Los campos que identifican el folio (orden, folio, origen, fecha y huella del archivo) entran en
 * `hash_cadena`, encadenada con la fila anterior. Tipo documental, asunto y la anulación pueden
 * cambiar sin romper la cadena: tipificar un folio o retirarlo no reescribe la historia del índice.
 *
 * @property int $expediente_id
 * @property int $documento_id
 * @property int $orden posición en el índice (igual al número de folio)
 * @property string|null $hash_sha256 huella del archivo del folio
 * @property Carbon|null $anulado_en fecha en que el folio se retiró del expediente
 * @property string|null $hash_anterior hash_cadena de la fila anterior del mismo expediente
 * @property string $hash_cadena sha256 de la fila encadenada con hash_anterior
 */
class OfiArchivoExpedienteIndice extends Model
{
    protected $table = 'ofi_archivo_expediente_indice';

    /** Campos que cubre la huella encadenada; el resto se puede actualizar. */
    public const CAMPOS_ENCADENADOS = [
        'expediente_id', 'documento_id', 'orden', 'numero_folio', 'origen', 'fecha_incorporacion', 'hash_sha256',
    ];

    protected $fillable = [
        'expediente_id',
        'documento_id',
        'orden',
        'numero_folio',
        'tipo_documental_id',
        'tipo_documental',
        'origen',
        'asunto',
        'fecha_documento',
        'fecha_incorporacion',
        'hash_sha256',
        'algoritmo',
        'formato',
        'tamano_bytes',
        'anulado_en',
        'motivo_anulacion',
        'anulado_por',
        'hash_anterior',
        'hash_cadena',
    ];

    protected $casts = [
        'expediente_id' => 'integer',
        'documento_id' => 'integer',
        'orden' => 'integer',
        'numero_folio' => 'integer',
        'tipo_documental_id' => 'integer',
        'tamano_bytes' => 'integer',
        'fecha_documento' => 'date',
        'fecha_incorporacion' => 'datetime',
        'anulado_en' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $fila) {
            if ($fila->isDirty(self::CAMPOS_ENCADENADOS) || $fila->isDirty(['hash_anterior', 'hash_cadena'])) {
                throw new \LogicException('Los datos encadenados del índice del expediente son inmutables.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Las filas del índice del expediente no se borran; se anulan.'));
    }

    public function documento()
    {
        return $this->belongsTo(OfiArchivoExpedienteDocumento::class, 'documento_id');
    }
}
