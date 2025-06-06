<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigListaDetalle extends Model
{
    use HasFactory;

    protected $table = 'config_listas_detalles';
    protected $fillable = ['lista_id', 'codigo', 'nombre'];

    public function lista()
    {
        return $this->belongsTo(ConfigLista::class, 'lista_id');
    }
}
