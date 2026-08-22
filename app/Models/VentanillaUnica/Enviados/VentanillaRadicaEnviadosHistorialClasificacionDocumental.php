<?php

namespace App\Models\VentanillaUnica\Enviados;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaEnviadosHistorialClasificacionDocumental extends Model
{
    protected $table = 'ventanilla_radica_enviados_historial_clasifica';

    protected $fillable = [
        'radica_enviados_id',
        'clasificacion_anterior_id',
        'clasificacion_nueva_id',
        'motivo',
        'user_id',
    ];

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaEnviados::class, 'radica_enviados_id');
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