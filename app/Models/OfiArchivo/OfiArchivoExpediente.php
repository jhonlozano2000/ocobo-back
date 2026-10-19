<?php

namespace App\Models\OfiArchivo;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Concerns\GuardaSnapshotDependencia;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use App\Services\OfiArchivo\ExpedienteEventosService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class OfiArchivoExpediente extends Model
{
    use GuardaSnapshotDependencia, HasFactory, SoftDeletes;

    protected $table = 'ofi_archivo_expedientes';

    /**
     * Permiso del archivo central para ver y gestionar expedientes de todas las dependencias (M08-03).
     */
    public const PERMISO_VER_TODOS = 'Gestion de Archivo -> Expedientes -> Ver Todos';

    protected $fillable = [
        'numero_expediente',
        'nombre_expediente',
        'anio',
        'dependencia_id',
        'dependencia_cod',
        'dependencia_nom',
        'serie_trd_id',
        'subserie_trd_id',
        'estado',
        'fase',
        'soporte',
        'nivel_acceso',
        'fundamento_acceso',
        'por_completar',
        'fecha_apertura',
        'fecha_cierre',
        'ubicacion_fisica',
        'ubicacion_id',
        'caja',
        'carpeta',
        'folios_fisicos',
        'observaciones_generales',
        'usuario_apertura_id',
        'usuario_cierre_id',
        'motivo_cierre',
        'motivo_reapertura',
        'total_folios_elec',
        'hash_indice',
        'trd_snapshot',
        'fecha_fin_gestion',
        'fecha_fin_central',
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'total_folios_elec' => 'integer',
        'folios_fisicos' => 'integer',
        'por_completar' => 'boolean',
        'trd_snapshot' => 'array',
        'fecha_fin_gestion' => 'date',
        'fecha_fin_central' => 'date',
    ];

    /** B16: dónde está el expediente; solo avanza (gestión → central → histórico). */
    public const FASES = ['gestion', 'central', 'historico'];

    /** B06: soporte del expediente; el híbrido es una sola unidad con parte física y electrónica. */
    public const SOPORTES = ['electronico', 'fisico', 'hibrido'];

    /** B06: Ley 1712/2014; clasificado y reservado exigen fundamento legal. */
    public const NIVELES_ACCESO = ['publico', 'clasificado', 'reservado'];

    /**
     * M09-06: el expediente conserva el código y el nombre de la dependencia productora.
     */
    protected static function booted(): void
    {
        static::saving(function (self $expediente) {
            if ($expediente->isDirty('dependencia_id')) {
                $expediente->capturarSnapshotDependencia();
            }
        });

        // B12: toda apertura y todo cambio del expediente quedan en su historial encadenado
        static::created(fn (self $expediente) => app(ExpedienteEventosService::class)->apertura($expediente));
        static::updated(fn (self $expediente) => app(ExpedienteEventosService::class)->cambio($expediente));
    }

    public function documentos()
    {
        return $this->hasMany(OfiArchivoExpedienteDocumento::class, 'expediente_id')->orderBy('numero_folio', 'asc');
    }

    /** B24: terceros relacionados con el expediente y su rol. */
    public function terceros()
    {
        return $this->hasMany(OfiArchivoExpedienteTercero::class, 'expediente_id');
    }

    /** B19: tomos (carpetas físicas) del expediente. */
    public function tomos()
    {
        return $this->hasMany(OfiArchivoExpedienteTomo::class, 'expediente_id')->orderBy('numero');
    }

    /** §3.10: préstamos y solicitudes de préstamo del expediente. */
    public function prestamos()
    {
        return $this->hasMany(OfiArchivoPrestamo::class, 'expediente_id');
    }

    /** B20: caja del catálogo topográfico donde está el expediente. */
    public function ubicacion()
    {
        return $this->belongsTo(OfiArchivoUbicacion::class, 'ubicacion_id');
    }

    /** B12: historial encadenado (solo inserción). */
    public function eventos()
    {
        return $this->hasMany(OfiArchivoExpedienteEvento::class, 'expediente_id');
    }

    public function dependencia()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'dependencia_id');
    }

    public function serieTrd()
    {
        return $this->belongsTo(ClasificacionDocumentalTRD::class, 'serie_trd_id');
    }

    public function subserieTrd()
    {
        return $this->belongsTo(ClasificacionDocumentalTRD::class, 'subserie_trd_id');
    }

    /**
     * B06/D6: lo que le falta al expediente para estar conforme (los anteriores a B06 no
     * declaraban nivel de acceso ni subserie).
     *
     * @return list<string>
     */
    public function pendientesDeCompletar(): array
    {
        $pendientes = [];
        if (! $this->nivel_acceso) {
            $pendientes[] = 'nivel_acceso';
        }
        if (! $this->subserie_trd_id && $this->serie_trd_id
            && ClasificacionDocumentalTRD::where('parent', $this->serie_trd_id)->where('tipo', 'SubSerie')->exists()) {
            $pendientes[] = 'subserie_trd_id';
        }

        return $pendientes;
    }

    public function usuarioApertura()
    {
        return $this->belongsTo(User::class, 'usuario_apertura_id');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(User::class, 'usuario_cierre_id');
    }

    /**
     * M08-14: fin de la retención en el archivo de gestión, contada desde el cierre.
     * B14: la fija el cierre con la TRD de ese momento; si el expediente se cerró antes de
     * B14, se calcula con la TRD actual (la subserie si la tiene; si no, la serie).
     */
    public function getFechaRetencionGestionFinAttribute(): ?Carbon
    {
        if ($this->fecha_fin_gestion) {
            return $this->fecha_fin_gestion->copy();
        }

        $trd = $this->trdRetencion();

        return $this->fecha_cierre && $trd ? $this->fecha_cierre->copy()->addYears((int) $trd->a_g) : null;
    }

    /**
     * M08-14: fin de la retención total (gestión + central), contada desde el cierre.
     */
    public function getFechaRetencionTotalFinAttribute(): ?Carbon
    {
        if ($this->fecha_fin_central) {
            return $this->fecha_fin_central->copy();
        }

        $trd = $this->trdRetencion();

        return $this->fecha_cierre && $trd ? $this->fecha_cierre->copy()->addYears((int) $trd->a_g + (int) $trd->a_c) : null;
    }

    /**
     * B14: TRD que rige la retención y la disposición del expediente: la instantánea del
     * cierre si la tiene; si no, la subserie actual o, sin subserie, la serie.
     */
    public function trdRetencion(): ?ClasificacionDocumentalTRD
    {
        if ($this->trd_snapshot) {
            return (new ClasificacionDocumentalTRD)->forceFill(collect($this->trd_snapshot)
                ->only(['tipo', 'cod', 'nom', 'version_id', 'a_g', 'a_c', 'ct', 'e', 'm_d', 's', 'disposicion_final', 'procedimiento'])
                ->put('id', $this->trd_snapshot['trd_id'] ?? null)
                ->all());
        }

        return $this->subserie_trd_id ? ($this->subserieTrd ?? $this->serieTrd) : $this->serieTrd;
    }

    public function scopeDocumentosActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * M08-03: sin el permiso PERMISO_VER_TODOS, solo los expedientes de la dependencia del
     * usuario (y de sus oficinas y dependencias hijas) o abiertos por él.
     */
    public function scopeVisiblesPara(Builder $query, User $user): Builder
    {
        if (self::puedeVerTodos($user)) {
            return $query;
        }

        $dependencias = self::dependenciasDelUsuario($user);

        return $query->where(function ($q) use ($user, $dependencias) {
            $q->where('usuario_apertura_id', $user->id);
            if ($dependencias !== []) {
                $q->orWhereIn('dependencia_id', $dependencias);
            }
        });
    }

    public static function puedeVerTodos(User $user): bool
    {
        try {
            return $user->hasPermissionTo(self::PERMISO_VER_TODOS);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * Ids de la dependencia más cercana al cargo activo del usuario y de todos sus nodos
     * descendientes. Vacío si el usuario no tiene cargo activo.
     *
     * @return array<int, int>
     */
    public static function dependenciasDelUsuario(User $user): array
    {
        $cargo = UserCargo::cargoActivoDelUsuario($user->id)?->cargo;
        if (! $cargo) {
            return [];
        }

        // getJerarquiaCompleta() va de la raíz al cargo; se toma la Dependencia más cercana al cargo
        $dependenciaId = null;
        foreach (array_reverse($cargo->getJerarquiaCompleta()) as $nodo) {
            if ($nodo['tipo'] === 'Dependencia') {
                $dependenciaId = $nodo['id'];
                break;
            }
        }
        if (! $dependenciaId) {
            return [];
        }

        return array_merge([$dependenciaId], CalidadOrganigrama::descendientes($dependenciaId)->pluck('id')->all());
    }

    public static function puedeUsarDependencia(User $user, int|string $dependenciaId): bool
    {
        return self::puedeVerTodos($user) || in_array((int) $dependenciaId, self::dependenciasDelUsuario($user), true);
    }
}
