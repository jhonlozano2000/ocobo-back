<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * B21: folio cuyo archivo no coincide con su huella, falta o no tiene huella. Queda abierto hasta
 * que el archivo lo resuelve con una justificación; un folio no tiene dos incidentes abiertos.
 */
class OfiArchivoIntegridadIncidente extends Model
{
    public $timestamps = false;

    protected $table = 'ofi_archivo_integridad_incidentes';

    protected $fillable = [
        'documento_id', 'expediente_id', 'revision_id', 'tipo', 'estado', 'detectado_at',
        'resuelto_at', 'resuelto_por_id', 'resolucion', 'resultado_al_resolver',
    ];

    protected $casts = [
        'detectado_at' => 'datetime',
        'resuelto_at' => 'datetime',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoExpedienteDocumento::class, 'documento_id');
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoIntegridadRevision::class, 'revision_id');
    }

    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por_id');
    }
}
