<?php

namespace App\Models\OfiArchivo;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfiArchivoExpediente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ofi_archivo_expedientes';

    protected $fillable = [
        'numero_expediente',
        'nombre_expediente',
        'dependencia_id',
        'serie_trd_id',
        'estado',
        'fecha_apertura',
        'fecha_cierre',
        'deposito',
        'caja',
        'carpeta',
        'folios_fisicos',
        'observacion_1',
        'observacion_2',
        'observacion_3',
        'usuario_apertura_id',
        'total_folios_elec',
        'hash_indice',
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'total_folios_elec' => 'integer',
        'folios_fisicos' => 'integer',
    ];

    public function documentos()
    {
        return $this->hasMany(OfiArchivoExpedienteDocumento::class, 'expediente_id')->orderBy('numero_folio', 'asc');
    }

    public function dependencia()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'dependencia_id');
    }

    public function serieTrd()
    {
        return $this->belongsTo(ClasificacionDocumentalTRD::class, 'serie_trd_id');
    }

    public function usuarioApertura()
    {
        return $this->belongsTo(User::class, 'usuario_apertura_id');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(User::class, 'usuario_cierre_id');
    }

    public function scopeDocumentosActivos($query)
    {
        return $query->where('activo', true);
    }
}
