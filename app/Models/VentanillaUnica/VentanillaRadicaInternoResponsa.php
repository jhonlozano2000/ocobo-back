<?php

namespace App\Models\VentanillaUnica;

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
}
