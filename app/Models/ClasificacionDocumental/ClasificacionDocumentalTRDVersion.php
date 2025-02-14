<?php

namespace App\Models\ClasificacionDocumental;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClasificacionDocumentalTRDVersion extends Model
{
    use HasFactory;

    protected $table = 'clasificacion_documental_trd_versiones';

    protected $fillable = [
        'dependencia_id',
        'version',
        'estado_version',
        'observaciones',
        'aprobado_por'
    ];

    public function dependencia()
    {
        return $this->belongsTo(\App\Models\Calidad\CalidadOrganigrama::class, 'dependencia_id');
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'aprobado_por');
    }

    public function trds()
    {
        return $this->hasMany(\App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD::class, 'version', 'version')
            ->where('dependencia_id', $this->dependencia_id);
    }
}
