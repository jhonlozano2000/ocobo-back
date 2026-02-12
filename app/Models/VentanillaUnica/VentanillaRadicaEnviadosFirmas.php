<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaEnviadosFirmas extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_enviados_firmas';

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
