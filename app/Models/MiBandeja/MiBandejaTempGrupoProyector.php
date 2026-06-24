<?php

namespace App\Models\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para proyectores de grupos colaborativos temporales.
 * Representa a un usuario proyector dentro de un grupo de trabajo temporal.
 */
class MiBandejaTempGrupoProyector extends Model
{
    use HasFactory;

    protected $table = 'mi_bandeja_temp_grupo_proyectores';

    protected $fillable = [
        'grupo_id',
        'user_id',
        'cargo_id',
        'subio_plantilla',
        'descargo_plantilla',
        'estado_tarea',
        'fechor_terminado',
    ];

    protected $casts = [
        'subio_plantilla' => 'boolean',
        'descargo_plantilla' => 'boolean',
        'estado_tarea' => 'string',
        'fechor_terminado' => 'datetime',
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
     * Relación con el usuario proyector.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el cargo del proyector.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Calidad\CalidadOrganigrama::class, 'cargo_id');
    }

    /**
     * Verifica si el proyector ha terminado su trabajo.
     *
     * @return bool true si el proyector ha terminado, false de lo contrario
     */
    public function estaTerminado(): bool
    {
        return $this->estado_tarea === 'cumplido';
    }
}