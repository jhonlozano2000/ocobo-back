<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigSede extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'direccion',
        'telefono',
        'email',
        'ubicacion',
        'divi_poli_id',
        'estado',
        'numeracion_unificada'
    ];

    /**
     * Obtiene la división política asociada a esta sede.
     */
    public function divisionPolitica()
    {
        return $this->belongsTo(ConfigDiviPoli::class, 'divi_poli_id');
    }

    /**
     * Obtiene las ventanillas asociadas a esta sede.
     */
    public function ventanillas()
    {
        return $this->hasMany(ConfigVentanilla::class);
    }
}
