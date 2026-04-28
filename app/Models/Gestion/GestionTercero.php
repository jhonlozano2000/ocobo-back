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

    /**
     * Los atributos que deben ser encriptados.
     * ISO 27001 A.10.1 - Controles criptográficos
     * Ley 1581/2012 - Protección de datos personales (Habeas Data)
     *
     * @var array<string, string>
     */
    protected $casts = [
        'num_docu_nit' => 'encrypted',
        'direccion' => 'encrypted',
        'telefono' => 'encrypted',
        'email' => 'encrypted',
    ];

    public function divisionPolitica()
    {
        return $this->belongsTo(ConfigDiviPoli::class, 'divi_poli_id');
    }
}
