<?php

namespace App\Models\VentanillaUnica\Enviados;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaEnviadosProyectores extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_enviados_proyectores';

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

    public function getInfoProyector(): ?array
    {
        $user = $this->relationLoaded('userCargo') ? $this->userCargo?->user : null;
        $cargo = $this->relationLoaded('userCargo') ? $this->userCargo?->cargo : null;
        
        if (!$user) return null;
        
        return [
            'id' => $this->id,
            'usuario' => $user ? [
                'id' => $user->id,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
            ] : null,
            'cargo' => $cargo ? [
                'id' => $cargo->id,
                'nombre' => $cargo->nom_organico,
            ] : null,
        ];
    }
}
