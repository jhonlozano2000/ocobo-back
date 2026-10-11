<?php

namespace App\Models\OfiArchivo;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class OfiArchivoExpediente extends Model
{
    use HasFactory, SoftDeletes;

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
        'serie_trd_id',
        'subserie_trd_id',
        'estado',
        'soporte',
        'fecha_apertura',
        'fecha_cierre',
        'ubicacion_fisica',
        'caja',
        'carpeta',
        'folios_fisicos',
        'observaciones_generales',
        'usuario_apertura_id',
        'usuario_cierre_id',
        'motivo_cierre',
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
