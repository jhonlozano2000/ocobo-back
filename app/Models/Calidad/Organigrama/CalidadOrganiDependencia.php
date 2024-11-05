<?php

namespace App\Models\Calidad\Organigrama;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalidadOrganiDependencia extends Model
{
    use HasFactory;

    public function oficinas()
    {
        return $this->hasMany(CalidadOrganiOficina::class, 'dependencia_id');
    }
}
