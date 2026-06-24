<?php

namespace App\Models\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\OfiArchivo\OfiArchivoPlantillaDocumento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo para grupos colaborativos temporales en Mi Bandeja.
 * Representa un grupo de trabajo temporal con responsables, firmantes y proyectores.
 */
class MiBandejaTemp extends Model
{
    use HasFactory;

    protected $table = 'mi_bandeja_temp';

    protected $fillable = [
        'nombre',
        'descripcion',
        'radicado_id',
        'radicado_tipo',
        'estado',
        'estado_grupo',
        'usua_crea_id',
        'usua_crea_plantilla_id',
        'plantilla_id',
        'asunto',
        'con_copia',
        'anexos',
        'plantilla_cargada',
        'respuesta_final',
    ];

    protected $casts = [
        'con_copia' => 'array',
        'anexos' => 'array',
        'plantilla_cargada' => 'boolean',
        'estado' => 'string',
        'estado_grupo' => 'string',
        'radicado_tipo' => 'string',
    ];

    /**
     * Relación con el radicado recibido asociado.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function radicadoRecibido(): BelongsTo
    {
        return $this->belongsTo(\App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci::class, 'radicado_id');
    }

    /**
     * Relación con el radicado enviado asociado.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function radicadoEnviado(): BelongsTo
    {
        return $this->belongsTo(\App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviado::class, 'radicado_id');
    }

    /**
     * Relación con el radicado interno asociado.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function radicadoInterno(): BelongsTo
    {
        return $this->belongsTo(\App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno::class, 'radicado_id');
    }

    /**
     * Relación con la plantilla de documento oficial asociada.
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoPlantillaDocumento::class, 'plantilla_id');
    }

    /**
     * Relación con las versiones del archivo del grupo.
     */
    public function versiones(): HasMany
    {
        return $this->hasMany(MiBandejaTempArchivoVersion::class, 'grupo_id');
    }

    /**
     * Relación con la última versión del archivo.
     */
    public function ultimaVersion(): HasOne
    {
        return $this->hasOne(MiBandejaTempArchivoVersion::class, 'grupo_id')
            ->latestOfMany('id');
    }

    /**
     * Relación con las notas del grupo.
     */
    public function notas(): HasMany
    {
        return $this->hasMany(MiBandejaTempNota::class, 'grupo_id');
    }

    /**
     * Relación con el usuario que creó el grupo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usua_crea_id');
    }

    /**
     * Relación con el usuario que cargó la plantilla.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plantillaCargadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usua_crea_plantilla_id');
    }

    /**
     * Relación con los responsables del grupo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function responsables(): HasMany
    {
        return $this->hasMany(MiBandejaTempGrupoResponsable::class, 'grupo_id');
    }

    /**
     * Relación con los firmantes del grupo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function firmantes(): HasMany
    {
        return $this->hasMany(MiBandejaTempGrupoFirmante::class, 'grupo_id');
    }

    /**
     * Relación con los proyectores del grupo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function proyectores(): HasMany
    {
        return $this->hasMany(MiBandejaTempGrupoProyector::class, 'grupo_id');
    }

    /**
     * Relación con los adjuntos del grupo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function adjuntos(): HasMany
    {
        return $this->hasMany(MiBandejaTempGrupoArchiAdjunto::class, 'grupo_id');
    }

    /**
     * Obtiene el radicado asociado según su tipo.
     *
     * @return \Illuminate\Database\Eloquent\Model|null Modelo del radicado o null si no existe
     */
    public function getRadicadoAttribute()
    {
        return match ($this->radicado_tipo) {
            'recibido' => $this->radicadoRecibido,
            'enviado' => $this->radicadoEnviado,
            'interno' => $this->radicadoInterno,
            default => null,
        };
    }

    /**
     * Verifica si todos los miembros del grupo han terminado su trabajo.
     *
     * @return bool true si todos los miembros han terminado, false de lo contrario
     */
    public function todosTerminados(): bool
    {
        $responsables = $this->responsables->count();
        $firmantes = $this->firmantes->count();
        $proyectores = $this->proyectores->count();

        if ($responsables === 0 && $firmantes === 0 && $proyectores === 0) {
            return false;
        }

        $todosResponsables = $this->responsables->every(fn ($r) => $r->estado_tarea === 'cumplido');
        $todosFirmantes = $this->firmantes->every(fn ($f) => $f->estado_tarea === 'cumplido' && $f->fechor_firmado !== null);
        $todosProyectores = $this->proyectores->every(fn ($p) => $p->estado_tarea === 'cumplido');

        return $todosResponsables && $todosFirmantes && $todosProyectores;
    }
}