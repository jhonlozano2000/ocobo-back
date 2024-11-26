<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigListaDetalle extends Model
{
    use HasFactory;

    protected $table = 'config_listas_detalles';
    protected $filllable = ['lista_id', 'codigo', 'nombre'];
}
