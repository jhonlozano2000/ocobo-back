<?php

namespace App\Models\VentanillaUnica\Internos;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaInternosHistorialClasificacionDocumental extends Model
{
    protected $table = 'ventanilla_radica_internos_historial_clasifica';

    protected $fillable = [
        'radica_interno_id',
        'clasificacion_anterior_id',
        'clasificacion_nueva_id',
        'motivo',
        'user_id',
    ];

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaInterno::class, 'radica_interno_id');
    }

    public function clasificacionAnterior()
    {
        return $this->belongsTo(ClasificacionDocumentalTRD::class, 'clasificacion_anterior_id');
    }

    public function clasificacionNueva()
    {
        return $this->belongsTo(ClasificacionDocumentalTRD::class, 'clasificacion_nueva_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}