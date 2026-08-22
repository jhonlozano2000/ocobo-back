<?php

namespace App\Models\VentanillaUnica\Internos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaInternoRespuesta extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_internos_respuestas';

    protected $fillable = [
        'radica_interno_id',
        'radica_interno_respuesta_id',
    ];

    /**
     * Radicado interno padre (el que recibe la respuesta).
     */
    public function radicadoInterno()
    {
        return $this->belongsTo(VentanillaRadicaInterno::class, 'radica_interno_id');
    }

    /**
     * Radicado interno que funciona como respuesta (debe tener fec_venci).
     */
    public function radicadoInternoRespuesta()
    {
        return $this->belongsTo(VentanillaRadicaInterno::class, 'radica_interno_respuesta_id');
    }
}
