<?php

namespace App\Models\VentanillaUnica\Internos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaInternoDestinatarios extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_internos_destina';

    protected $fillable = [
        'radica_interno_id',
        'users_cargos_id',
        'visto',
        'fechor_visto'
    ];

    public function radicaInterno()
    {
        return $this->belongsTo(VentanillaRadicaInterno::class, 'radica_interno_id');
    }

    public function userCargo()
    {
        return $this->belongsTo(\App\Models\ControlAcceso\UserCargo::class, 'users_cargos_id');
    }

    public function marcarComoVisto(): void
    {
        if (!$this->fechor_visto) {
            $this->update(['fechor_visto' => now()]);
        }
    }

    public function isVisto(): bool
    {
        return (bool) $this->visto;
    }

    public function desmarcarComoVisto(): void
    {
        $this->update(['visto' => false]);
    }

    public function getInfoDestinatario(): ?array
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
            'visto' => $this->visto,
            'fechor_visto' => $this->fechor_visto,
            'created_at' => $this->created_at,
        ];
    }
}
