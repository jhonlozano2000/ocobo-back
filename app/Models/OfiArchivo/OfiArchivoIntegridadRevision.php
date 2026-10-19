<?php

namespace App\Models\OfiArchivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * B21: resultado de recalcular la huella de un folio en una verificación.
 */
class OfiArchivoIntegridadRevision extends Model
{
    public const INTEGRO = 'integro';

    public const ALTERADO = 'alterado';

    public const FALTANTE = 'faltante';

    public const SIN_HUELLA = 'sin_huella';

    /** Radicado incorporado sin archivo digital ni huella (soporte físico): no hay nada que verificar. */
    public const SIN_ARCHIVO = 'sin_archivo';

    /** Resultados que abren un incidente. */
    public const PROBLEMAS = [self::ALTERADO, self::FALTANTE, self::SIN_HUELLA];

    public const UPDATED_AT = null;

    protected $table = 'ofi_archivo_integridad_revisiones';

    protected $fillable = ['verificacion_id', 'documento_id', 'expediente_id', 'resultado', 'hash_esperado', 'hash_calculado'];

    public function verificacion(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoIntegridadVerificacion::class, 'verificacion_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoExpedienteDocumento::class, 'documento_id');
    }
}
