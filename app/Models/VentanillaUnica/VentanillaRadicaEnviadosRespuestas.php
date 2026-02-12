<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaEnviadosRespuestas extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_enviados_respuestas';

    protected $fillable = [
        'radica_enviado_id',
        'users_cargos_id',
    ];

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaEnviados::class, 'radica_enviado_id');
    }

    public function userCargo()
    {
        return $this->belongsTo(\App\Models\ControlAcceso\UserCargo::class, 'users_cargos_id');
    }
}
