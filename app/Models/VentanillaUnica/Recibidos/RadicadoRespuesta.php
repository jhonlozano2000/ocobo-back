<?php

namespace App\Models\VentanillaUnica\Recibidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadicadoRespuesta extends Model
{
    protected $table = 'ventanilla_radica_reci_respuestas';

    protected $fillable = [
        'radicado_id',
        'titulo',
        'contenido',
        'contenido_json',
        'user_crea_id',
        'user_actualiza_id',
    ];

    protected $casts = [
        'contenido_json' => 'array',
    ];

    public function radicado(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
    }

    public function usuarioCrea(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_crea_id');
    }

    public function usuarioActualiza(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_actualiza_id');
    }
}
