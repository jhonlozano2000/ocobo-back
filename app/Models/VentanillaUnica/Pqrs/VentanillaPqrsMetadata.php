<?php

namespace App\Models\VentanillaUnica\Pqrs;

use App\Models\Configuracion\FileClassificationLevel;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentanillaPqrsMetadata extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_pqrs_metadata';

    protected $fillable = [
        'archivo_id',
        'pqrs_id',
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
        'fecha_creacion_documento',
        'fecha_modificacion',
        'fecha_vencimiento',
        'clasificacion_id',
        'clasificacion_ruta',
        'clasificacion_serie',
        'clasificacion_subserie',
        'clasificacion_tipo_doc',
        'hash_sha256_archivo',
        'algoritmo_hash',
        'tipo_archivo',
        'num_radicado',
        'asunto',
        'estado_registro',
    ];

    protected $casts = [
        'palabras_clave' => 'array',
        'propagacion_clasificacion' => 'boolean',
        'es_version_actual' => 'boolean',
        'fecha_creacion_documento' => 'datetime',
        'fecha_modificacion' => 'datetime',
        'fecha_vencimiento' => 'date',
    ];

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciArchivo::class, 'archivo_id');
    }

    public function pqrs(): BelongsTo
    {
        return $this->belongsTo(VentanillaPqrs::class, 'pqrs_id');
    }

    public function radicado(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
    }

    public function nivelClasificacion(): BelongsTo
    {
        return $this->belongsTo(FileClassificationLevel::class, 'nivel_clasificacion_id');
    }

    public function duenoDocumento(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dueno_documento_id');
    }

    public function custodioActual(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodio_actual_id');
    }
}
