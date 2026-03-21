<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaInternoDestinatarios extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_interno_destina';

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
}
