<?php

namespace App\Models\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para firmantes de grupos colaborativos temporales.
 * Representa a un usuario firmante dentro de un grupo de trabajo temporal.
 */
class MiBandejaTempGrupoFirmante extends Model
{
    use HasFactory;

    protected $table = 'mi_bandeja_temp_grupo_firmantes';

    protected $fillable = [
        'grupo_id',
        'user_id',
        'cargo_id',
        'orden_firma',
        'subio_plantilla',
        'descargo_plantilla',
        'estado_tarea',
        'fechor_terminado',
        'fechor_firmado',
    ];

    protected $casts = [
        'orden_firma' => 'integer',
        'subio_plantilla' => 'boolean',
        'descargo_plantilla' => 'boolean',
        'estado_tarea' => 'string',
        'fechor_terminado' => 'datetime',
        'fechor_firmado' => 'datetime',
    ];

    /**
     * Relación con el grupo colaborativo temporal.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(MiBandejaTemp::class, 'grupo_id');
    }

    /**
     * Relación con el usuario firmante.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el cargo del firmante.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Calidad\CalidadOrganigrama::class, 'cargo_id');
    }

    /**
     * Verifica si el firmante ha terminado su trabajo y ha firmado.
     *
     * @return bool true si el firmante ha terminado y firmado, false de lo contrario
     */
    public function estaTerminado(): bool
    {
        return $this->fechor_terminado !== null && $this->fechor_firmado !== null;
    }
}