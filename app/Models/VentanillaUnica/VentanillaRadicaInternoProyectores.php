<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaInternoProyectores extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_interno_proyectores';

    protected $fillable = [
        'radica_interno_id',
        'users_cargos_id',
    ];

    public function radicaInterno()
    {
        return $this->belongsTo(VentanillaRadicaInterno::class, 'radica_interno_id');
    }

    public function userCargo()
    {
        return $this->belongsTo(\App\Models\ControlAcceso\UserCargo::class, 'users_cargos_id');
    }
}
