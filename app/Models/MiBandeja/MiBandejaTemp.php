<?php

namespace App\Models\MiBandeja;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\OfiArchivo\OfiArchivoPlantillaDocumento;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo para grupos colaborativos temporales en Mi Bandeja.
 * Representa un grupo de trabajo temporal con revisores, firmantes, proyectores y aprobadores.
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
        'documento_id',
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
     */
    public function radicadoRecibido(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
    }

    /**
     * Relación con el radicado enviado asociado.
     */
    public function radicadoEnviado(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaEnviados::class, 'radicado_id');
    }

    /**
     * Relación con el radicado interno asociado.
     */
    public function radicadoInterno(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaInterno::class, 'radicado_id');
    }

    /**
     * Relación con la plantilla de documento oficial asociada.
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoPlantillaDocumento::class, 'plantilla_id');
    }

    /**
     * Documento de Mis Documentos (editor en línea) que el grupo trabaja, si lo hay.
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
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
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usua_crea_id');
    }

    /**
     * Relación con el usuario que cargó la plantilla.
     */
    public function plantillaCargadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usua_crea_plantilla_id');
    }

    /**
     * Relación con los revisores del grupo.
     */
    public function revisores(): HasMany
    {
        return $this->hasMany(MiBandejaTempGrupoRevisor::class, 'grupo_id');
    }

    /**
     * Relación con los aprobadores del grupo.
     */
    public function aprobadores(): HasMany
    {
        return $this->hasMany(MiBandejaTempGrupoAprobador::class, 'grupo_id');
    }

    /**
     * Relación con los firmantes del grupo.
     */
    public function firmantes(): HasMany
    {
        return $this->hasMany(MiBandejaTempGrupoFirmante::class, 'grupo_id');
    }

    /**
     * Relación con los proyectores del grupo.
     */
    public function proyectores(): HasMany
    {
        return $this->hasMany(MiBandejaTempGrupoProyector::class, 'grupo_id');
    }

    /**
     * Relación con los adjuntos del grupo.
     */
    public function adjuntos(): HasMany
    {
        return $this->hasMany(MiBandejaTempGrupoArchiAdjunto::class, 'grupo_id');
    }

    /**
     * Registro de lecturas de notas por usuario.
     */
    public function lecturasNotas(): HasMany
    {
        return $this->hasMany(MiBandejaTempNotaLectura::class, 'grupo_id');
    }

    /**
     * Eventos de firma electrónica asociados al grupo colaborativo temporal (ISO 27001).
     */
    public function firmaEventos()
    {
        return $this->morphMany(FirmaEvento::class, 'documentable');
    }

    /**
     * Obtiene el radicado asociado según su tipo.
     *
     * @return Model|null Modelo del radicado o null si no existe
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
        $revisores = $this->revisores->count();
        $firmantes = $this->firmantes->count();
        $proyectores = $this->proyectores->count();
        $aprobadores = $this->aprobadores->count();

        if ($revisores === 0 && $firmantes === 0 && $proyectores === 0 && $aprobadores === 0) {
            return false;
        }

        $todosRevisores = $this->revisores->every(fn ($r) => $r->estado_tarea === 'cumplido');
        $todosFirmantes = $this->firmantes->every(fn ($f) => $f->estado_tarea === 'cumplido' && $f->fechor_firmado !== null);
        $todosProyectores = $this->proyectores->every(fn ($p) => $p->estado_tarea === 'cumplido');
        $todosAprobadores = $this->aprobadores->every(fn ($a) => $a->estado_tarea === 'cumplido');

        return $todosRevisores && $todosFirmantes && $todosProyectores && $todosAprobadores;
    }

    /**
     * Verifica si todos los firmantes asignados al grupo han suscrito el documento.
     *
     * @return bool true si todos los firmantes han firmado y existe al menos uno, false de lo contrario
     */
    public function todosFirmantesHanFirmado(): bool
    {
        $totalFirmantes = $this->firmantes()->count();
        if ($totalFirmantes === 0) {
            return false;
        }

        return $this->firmantes()->whereNotNull('fechor_firmado')->count() === $totalFirmantes;
    }
}
