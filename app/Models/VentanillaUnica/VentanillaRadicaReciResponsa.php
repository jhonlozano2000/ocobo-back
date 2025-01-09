<?php

namespace App\Models\VentanillaUnica;

use App\Models\ControlAcceso\UsersCargo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaReciResponsa extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci_responsa';

    protected $fillable = [
        'radica_reci_id',
        'users_cargos_id',
        'custodio',
        'fechor_visto',
    ];

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radica_reci_id');
    }

    public function usuarioCargo()
    {
        return $this->belongsTo(UsersCargo::class, 'users_cargos_id');
    }
}
