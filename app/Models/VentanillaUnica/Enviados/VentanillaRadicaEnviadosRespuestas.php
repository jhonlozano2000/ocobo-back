<?php

namespace App\Models\VentanillaUnica\Enviados;

use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaEnviadosRespuestas extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_enviados_respuestas';

    protected $fillable = [
        'radica_enviado_id',
        'radica_reci_id',
    ];

    public function radicadoEnviado()
    {
        return $this->belongsTo(VentanillaRadicaEnviados::class, 'radica_enviado_id');
    }

    public function radicadoRecibido()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radica_reci_id');
    }
}
