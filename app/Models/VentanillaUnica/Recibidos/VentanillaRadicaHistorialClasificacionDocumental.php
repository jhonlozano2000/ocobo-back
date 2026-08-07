<?php

namespace App\Models\VentanillaUnica\Recibidos;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaHistorialClasificacionDocumental extends Model
{
    protected $table = 'ventanilla_radica_historial_clasificacion_documental';

    protected $fillable = [
        'radicado_id',
        'clasificacion_anterior_id',
        'clasificacion_nueva_id',
        'motivo',
        'user_id',
    ];

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
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
