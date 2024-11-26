<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigDiviPoli extends Model
{
    use HasFactory;

    protected $table = 'config_divi_poli';
    protected $fillable = ['parent', 'codigo', 'nombre', 'tipo'];
}
