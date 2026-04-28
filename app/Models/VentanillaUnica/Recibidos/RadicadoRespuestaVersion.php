<?php

namespace App\Models\VentanillaUnica\Recibidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadicadoRespuestaVersion extends Model
{
    public $timestamps = false;
    
    protected $table = 'ventanilla_radica_reci_respuestas_version';

    protected $fillable = [
        'respuesta_id',
        'version',
        'contenido',
        'contenido_json',
        'user_id',
        'cambios_resumen',
        'created_at',
    ];

    protected $casts = [
        'contenido_json' => 'array',
        'version' => 'integer',
        'created_at' => 'datetime',
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