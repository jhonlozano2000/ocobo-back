<?php

namespace App\Models\OfiArchivo;

use App\Models\Gestion\GestionTercero;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * B24 (diseño de expedientes §2.1): tercero relacionado con un expediente y su rol en el trámite.
 */
class OfiArchivoExpedienteTercero extends Model
{
    protected $table = 'ofi_archivo_expediente_terceros';

    public const ROLES = ['titular', 'solicitante', 'contratista', 'beneficiario', 'apoderado', 'interesado'];

    protected $fillable = ['expediente_id', 'tercero_id', 'rol', 'usuario_id'];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(GestionTercero::class, 'tercero_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
