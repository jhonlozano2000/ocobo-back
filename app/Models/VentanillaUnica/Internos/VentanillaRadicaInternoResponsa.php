<?php

namespace App\Models\VentanillaUnica\Internos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaInternoResponsa extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_interno_responsa';

    protected $fillable = [
        'radica_interno_id',
        'users_cargos_id',
        'custodio',
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

    public function isCustodio(): bool
    {
        return (bool) $this->custodio;
    }

    public function marcarComoCustodio(): void
    {
        $this->update(['custodio' => true]);
    }

    public function desmarcarComoCustodio(): void
    {
        $this->update(['custodio' => false]);
    }

    public function getInfoResponsable(): ?array
    {
        if (!$this->relationLoaded('userCargo')) {
            $this->load(['userCargo.user', 'userCargo.cargo']);
        }

        $userCargo = $this->userCargo;
        if (!$userCargo) {
            return null;
        }

        $user = $userCargo->user;
        $cargo = $userCargo->cargo;

        return [
            'id' => $this->id,
            'radica_interno_id' => $this->radica_interno_id,
            'users_cargos_id' => $this->users_cargos_id,
            'custodio' => $this->custodio,
            'fechor_visto' => $this->fechor_visto?->format('Y-m-d H:i:s'),
            'user' => $user ? [
                'id' => $user->id,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'email' => $user->email,
            ] : null,
            'cargo' => $cargo ? [
                'id' => $cargo->id,
                'nom_organico' => $cargo->nom_organico,
            ] : null,
        ];
    }
}
