<?php

namespace App\Models\Calidad\Organigrama;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalidadOrganiOficina extends Model
{
    use HasFactory;

    // Relación con Dependencia (muchos a uno)
    public function dependencia()
    {
        return $this->belongsTo(CalidadOrganiDependencia::class, 'dependencia_id');
    }

    // Relación jerárquica con otras oficinas (auto-relación)
    public function subOficinas()
    {
        return $this->hasMany(CalidadOrganiOficina::class, 'oficina_id');
    }

    // Relación con la oficina padre (si existe)
    public function oficinaPadre()
    {
        return $this->belongsTo(CalidadOrganiOficina::class, 'oficina_id');
    }

    // Dentro del modelo Oficina
    public function cargos()
    {
        return $this->hasMany(CalidadOrganiCargo::class, 'oficina_id');
    }
}
