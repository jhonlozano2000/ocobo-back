<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigLista extends Model
{
    use HasFactory;

    protected $table = 'config_listas';
    protected $fillable = ['cod', 'nombre'];

    public function detalles()
    {
        return $this->hasMany(ConfigListaDetalle::class, 'lista_id');
    }
}
