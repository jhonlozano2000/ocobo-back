<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaEnviadosRespona extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_enviados_responsa';

    protected $fillable = [
        'radica_enviado_id',
        'users_cargos_id',
        'custodio',
        'fechor_visto',
    ];

    protected $casts = [
        'custodio' => 'boolean',
        'fechor_visto' => 'datetime',
    ];

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaEnviados::class, 'radica_enviado_id');
    }

    public function userCargo()
    {
        return $this->belongsTo(\App\Models\ControlAcceso\UserCargo::class, 'users_cargos_id');
    }

    public function scopeCustodios($query)
    {
        return $query->where('custodio', true);
    }

    public function scopeNoCustodios($query)
    {
        return $query->where('custodio', false);
    }

    public function isCustodio(): bool
    {
        return (bool) $this->custodio;
    }

    public function marcarComoVisto(): void
    {
        if (!$this->fechor_visto) {
            $this->update(['fechor_visto' => now()]);
        }
    }

    public function getInfoResponsable(): ?array
    {
        $userCargo = $this->relationLoaded('userCargo') ? $this->userCargo : $this->userCargo()->with(['user', 'cargo'])->first();

        if (!$userCargo) {
            return null;
        }

        $user = $userCargo->relationLoaded('user') ? $userCargo->user : null;
        $cargo = $userCargo->relationLoaded('cargo') ? $userCargo->cargo : null;

        return [
            'id' => $this->id,
            'custodio' => $this->custodio,
            'fechor_visto' => $this->fechor_visto,
            'fecha_asignacion' => $this->created_at,
            'usuario' => $user ? $user->getInfoUsuario() : null,
            'cargo' => $cargo ? [
                'id' => $cargo->id,
                'nombre' => $cargo->nom_organico,
                'codigo' => $cargo->cod_organico,
                'tipo' => $cargo->tipo,
            ] : null,
        ];
    }
}
