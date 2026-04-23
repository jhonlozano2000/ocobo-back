<?php

namespace App\Models\VentanillaUnica\Internos;

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

    public function getInfoProyector(): ?array
    {
        $user = $this->userCargo?->user;
        $cargo = $this->userCargo?->cargo;

        if (!$user) {
            return null;
        }

        return [
            'id' => $this->id,
            'radica_interno_id' => $this->radica_interno_id,
            'users_cargos_id' => $this->users_cargos_id,
            'usuario' => [
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'nombre_completo' => trim($user->nombres . ' ' . $user->apellidos),
            ],
            'cargo' => $cargo ? [
                'id' => $cargo->id,
                'nombre' => $cargo->nom_organico,
                'nom_organico' => $cargo->nom_organico,
                'codigo' => $cargo->cod_organico,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
