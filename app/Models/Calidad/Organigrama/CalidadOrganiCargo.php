<?php

namespace App\Models\Calidad\Organigrama;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalidadOrganiCargo extends Model
{
    use HasFactory;


    // Campos que se pueden llenar
    protected $fillable = [
        'nom_cargo',
        'descripcion',
        'estado',
        'oficina_id'
    ];

    // Relación con Oficina (muchos a uno)
    public function oficina()
    {
        return $this->belongsTo(CalidadOrganiOficina::class, 'oficina_id');
    }

    /* public function empleados()
    {
        return $this->hasMany(User::class);
    } */
}
