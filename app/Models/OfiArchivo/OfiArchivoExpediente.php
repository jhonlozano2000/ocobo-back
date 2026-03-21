<?php

namespace App\Models\OfiArchivo;

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

    /**
     * Relación con los documentos (Índice) del expediente.
     */
    public function documentos()
    {
        return $this->hasMany(OfiArchivoExpedienteDocumento::class, 'expediente_id')->orderBy('numero_folio', 'asc');
    }

    /**
     * Relación con la dependencia productora.
     */
    public function dependencia()
    {
        return $this->belongsTo(\App\Models\Calidad\CalidadOrganigrama::class, 'dependencia_id');
    }

    /**
     * Relación con la serie documental de la TRD.
     */
    public function serieTrd()
    {
        return $this->belongsTo(\App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD::class, 'serie_trd_id');
    }

    /**
     * Usuario que realizó la apertura del expediente.
     */
    public function usuarioApertura()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_apertura_id');
    }
}
