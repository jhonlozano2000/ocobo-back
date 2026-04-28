<?php

namespace App\Models\VentanillaUnica\Recibidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadicadoRespuestaParticipante extends Model
{
    protected $table = 'ventanilla_radica_reci_respuestas_participantes';

    protected $fillable = [
        'respuesta_id',
        'user_id',
        'rol',
        'puede_editar',
        'puede_revisar',
        'puede_aprobar',
        'fecha_asignacion',
    ];

    protected $casts = [
        'puede_editar' => 'boolean',
        'puede_revisar' => 'boolean',
        'puede_aprobar' => 'boolean',
        'fecha_asignacion' => 'datetime',
    ];

    public function respuesta(): BelongsTo
    {
        return $this->belongsTo(RadicadoRespuesta::class, 'respuesta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}