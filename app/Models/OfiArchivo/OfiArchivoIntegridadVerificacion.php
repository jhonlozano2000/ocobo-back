<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * B21: una corrida de verificación de huellas (muestra semanal, expediente o lote a transferir).
 */
class OfiArchivoIntegridadVerificacion extends Model
{
    public const ALCANCES = ['muestra', 'expediente', 'transferencia'];

    public $timestamps = false;

    protected $table = 'ofi_archivo_integridad_verificaciones';

    protected $fillable = [
        'alcance', 'expediente_id', 'porcentaje', 'revisados', 'incidentes', 'user_id', 'iniciada_at', 'terminada_at',
    ];

    protected $casts = [
        'porcentaje' => 'integer',
        'revisados' => 'integer',
        'incidentes' => 'integer',
        'iniciada_at' => 'datetime',
        'terminada_at' => 'datetime',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(OfiArchivoIntegridadRevision::class, 'verificacion_id');
    }
}
