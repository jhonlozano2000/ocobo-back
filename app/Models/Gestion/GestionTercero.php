<?php

namespace App\Models\Gestion;

use App\Models\Configuracion\ConfigDiviPoli;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GestionTercero extends Model
{
    use HasFactory;

    protected $table = 'gestion_terceros';

    protected $fillable = [
        'pais_id',
        'departamento_id',
        'municipio_id',
        'num_docu_nit',
        'nom_razo_soci',
        'direccion',
        'telefono',
        'email',
        'tipo',
        'notifica_email',
        'notifica_msm'
    ];

    public function pais()
    {
        return $this->belongsTo(ConfigDiviPoli::class, 'pais_id');
    }

    public function departamento()
    {
        return $this->belongsTo(ConfigDiviPoli::class, 'departamento_id');
    }

    public function municipio()
    {
        return $this->belongsTo(ConfigDiviPoli::class, 'municipio_id');
    }
}
