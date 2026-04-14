<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentanillaRadicaReciMetadata extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci_metadata';

    protected $fillable = [
        'archivo_id',
        'radicado_id',
        'nivel_clasificacion_id',
        'nivel_clasificacion',
        'propagacion_clasificacion',
        'titulo_documento',
        'descripcion',
        'palabras_clave',
        'version_numero',
        'version_descripcion',
        'es_version_actual',
        'dueno_documento_id',
        'custodio_actual_id',
        'responsable_clasificacion_id',
        'fecha_creacion_documento',
        'fecha_radicacion',
        'fecha_modificacion',
        'fecha_ultima_consulta',
        'fecha_vencimiento',
        'fecha_disposicion_final',
        'fecha_retencion_fin',
        'clasificacion_id',
        'clasificacion_ruta',
        'clasificacion_serie',
        'clasificacion_subserie',
        'clasificacion_tipo_doc',
        'hash_sha256_original',
        'hash_sha256_archivo',
        'firma_digital_hash',
        'algoritmo_hash',
        'control_acceso_nivel',
        'roles_autorizados',
        'usuarios_con_acceso',
        'requiere_autenticacion_adicional',
        'terminos_retention_id',
        'categoria_informacion',
        'es_registro_vital',
        'copias_backups',
        'num_radicado',
        'asunto',
        'tercero_nombre',
        'tercero_identificacion',
        'empresa_nombre',
        'sede_nombre',
        'departamento_origen',
        'medio_recepcion',
        'tipo_archivo',
        'estado_registro',
        'motivo_estado',
    ];

    protected $casts = [
        'palabras_clave' => 'array',
        'roles_autorizados' => 'array',
        'usuarios_con_acceso' => 'array',
        'copias_backups' => 'array',
        'propagacion_clasificacion' => 'boolean',
        'es_version_actual' => 'boolean',
        'requiere_autenticacion_adicional' => 'boolean',
        'es_registro_vital' => 'boolean',
        'fecha_creacion_documento' => 'datetime',
        'fecha_radicacion' => 'datetime',
        'fecha_modificacion' => 'datetime',
        'fecha_ultima_consulta' => 'datetime',
        'fecha_vencimiento' => 'date',
        'fecha_disposicion_final' => 'date',
        'fecha_retencion_fin' => 'date',
    ];

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaReciArchivo::class, 'archivo_id');
    }

    public function radicado(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
    }

    public function nivelClasificacion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Configuracion\FileClassificationLevel::class, 'nivel_clasificacion_id');
    }

    public function duenoDocumento(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'dueno_documento_id');
    }

    public function custodioActual(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'custodio_actual_id');
    }

    public function responsableClasificacion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'responsable_clasificacion_id');
    }
}
