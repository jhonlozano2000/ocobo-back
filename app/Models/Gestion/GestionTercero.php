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
        'divi_poli_id',
        'num_docu_nit',
        'nom_razo_soci',
        'direccion',
        'telefono',
        'email',
        'tipo',
        'notifica_email',
        'notifica_msm'
    ];

    public function divisionPolitica()
    {
        return $this->belongsTo(ConfigDiviPoli::class, 'divi_poli_id');
    }
}
