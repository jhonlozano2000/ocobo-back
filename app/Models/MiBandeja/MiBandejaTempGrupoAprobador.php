<?php

namespace App\Models\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiBandejaTempGrupoAprobador extends Model
{
    use HasFactory;

    protected $table = 'mi_bandeja_temp_grupo_aprobadores';

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

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(MiBandejaTemp::class, 'grupo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'cargo_id');
    }

    public function estaTerminado(): bool
    {
        return $this->estado_tarea === 'cumplido';
    }
}
