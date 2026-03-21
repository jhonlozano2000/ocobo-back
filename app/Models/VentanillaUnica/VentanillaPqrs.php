<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VentanillaPqrs extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ventanilla_pqrs';

    protected $fillable = [
        'radicado_id',
        'tipo_pqrs_id',
        'dependencia_responsable_id',
        'estado_tramite',
        'fecha_vencimiento',
        'fecha_vencimiento_original',
        'tiene_prorroga',
        'es_anonimo',
        'canal_preferido',
        'prioridad',
        'fecha_respuesta',
        'observaciones'
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'fecha_vencimiento_original' => 'date',
        'fecha_respuesta' => 'datetime',
        'es_anonimo' => 'boolean',
        'tiene_prorroga' => 'boolean'
    ];

    /**
     * Relación con el radicado original (1:1).
     */
    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
    }

    /**
     * Relación con el tipo de PQRS (Petición, Queja, etc).
     */
    public function tipoPqrs()
    {
        return $this->belongsTo(\App\Models\Configuracion\ConfigListaDetalle::class, 'tipo_pqrs_id');
    }

    /**
     * Oficina encargada de resolver la PQRS.
     */
    public function dependenciaResponsable()
    {
        return $this->belongsTo(\App\Models\Calidad\CalidadOrganigrama::class, 'dependencia_responsable_id');
    }
}
