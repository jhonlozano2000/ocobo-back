<?php

namespace App\Models\VentanillaUnica\Pqrs;

use App\Helpers\CalendarioHelper;
use App\Models\ControlAcceso\UserCargo;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class VentanillaPqrsOptimizedView extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_pqrs_view';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'fecha_vencimiento' => 'date',
        'ultima_visualizacion' => 'datetime',
        'total_archivos' => 'integer',
        'total_responsables' => 'integer',
        'total_custodios' => 'integer',
        'total_custodios_activos' => 'integer',
    ];

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('num_radicado', 'like', "%{$search}%")
                ->orWhere('detalle_solicitud', 'like', "%{$search}%")
                ->orWhere('tercero_nombre', 'like', "%{$search}%")
                ->orWhere('tercero_documento', 'like', "%{$search}%");
        });
    }

    public function scopeFechaEntre(Builder $query, ?string $desde, ?string $hasta): Builder
    {
        if ($desde) {
            $query->whereDate('created_at', '>=', $desde);
        }
        if ($hasta) {
            $query->whereDate('created_at', '<=', $hasta);
        }

        return $query;
    }

    public function scopeClasificacionDocumental(Builder $query, ?int $clasificacionId): Builder
    {
        if ($clasificacionId) {
            return $query->where('clasificacion_documental_trd_id', $clasificacionId);
        }

        return $query;
    }

    public function scopeTercero(Builder $query, ?int $terceroId): Builder
    {
        if ($terceroId) {
            return $query->where('gestion_tercero_id', $terceroId);
        }

        return $query;
    }

    public function scopeTipoPqrs(Builder $query, ?int $tipoId): Builder
    {
        if ($tipoId) {
            return $query->where('tipo_pqrs_id', $tipoId);
        }

        return $query;
    }

    public function scopeEstadoTramite(Builder $query, ?string $estado): Builder
    {
        if ($estado) {
            return $query->where('estado_tramite', $estado);
        }

        return $query;
    }

    public function scopePrioridad(Builder $query, ?string $prioridad): Builder
    {
        if ($prioridad) {
            return $query->where('prioridad', $prioridad);
        }

        return $query;
    }

    public function scopeMedioRecepcion(Builder $query, ?int $medioId): Builder
    {
        if ($medioId) {
            return $query->where('radicado_medio_recep_id', $medioId);
        }

        return $query;
    }

    public function scopeOrdenadoPorFecha(Builder $query, string $direccion = 'desc'): Builder
    {
        return $query->orderBy('created_at', $direccion);
    }

    public function scopeConPermisoJerarquico(Builder $query, $user = null): Builder
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return $query->whereRaw('1=0');
        }

        // M04-02: el bypass es un permiso propio; antes era 'Listar', el mismo que exige la ruta
        if (self::puedeVerTodos($user)) {
            return $query;
        }

        // Responsables del radicado asociado (aplica aunque el usuario no tenga cargo activo)
        $responsable = function ($subQ) use ($user) {
            $subQ->select('r.radica_reci_id')
                ->from('ventanilla_radica_reci_responsa as r')
                ->join('users_cargos as uc', 'uc.id', '=', 'r.users_cargos_id')
                ->where('uc.user_id', $user->id);
        };

        // Obtener códigos de la jerarquía del usuario
        $cargoActivo = UserCargo::cargoActivoDelUsuario($user->id);
        if (! $cargoActivo || ! $cargoActivo->cargo) {
            return $query->where(function ($q) use ($user, $responsable) {
                $q->where('usuario_crea', $user->id)
                    ->orWhereIn('ventanilla_radica_reci_id', $responsable);
            });
        }

        // Subir por la jerarquía (padres)
        $codigosJerarquia = [];
        $cargo = $cargoActivo->cargo;
        while ($cargo && is_object($cargo)) {
            $codigosJerarquia[] = $cargo->cod_organico;
            $parent = $cargo->parent;
            $cargo = is_object($parent) ? $parent : null;
        }

        // Bajar por la jerarquía (hijos recursivos)
        $codigosJerarquia = array_merge(
            $codigosJerarquia,
            $cargoActivo->cargo->getDescendientesCodigos()
        );
        $codigosJerarquia = array_unique($codigosJerarquia);

        // Aplicar filtro: creador O responsable O usuario en jerarquía
        return $query->where(function ($q) use ($user, $codigosJerarquia, $responsable) {
            $q->where('usuario_crea', $user->id)
                ->orWhereIn('ventanilla_radica_reci_id', $responsable);

            $q->orWhereIn('usuario_crea', function ($subQ) use ($codigosJerarquia) {
                $subQ->select('users_cargos.user_id')
                    ->from('users_cargos')
                    ->join('calidad_organigrama', 'users_cargos.cargo_id', '=', 'calidad_organigrama.id')
                    ->whereIn('calidad_organigrama.cod_organico', $codigosJerarquia)
                    ->where('users_cargos.estado', true)
                    ->whereNull('users_cargos.fecha_fin');
            });
        });
    }

    public static function puedeVerTodos($user): bool
    {
        try {
            return $user->hasPermissionTo(VentanillaPqrs::PERMISO_VER_TODOS);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function scopeConArchivos(Builder $query): Builder
    {
        return $query->where('total_archivos', '>', 0);
    }

    public function scopeSinArchivos(Builder $query): Builder
    {
        return $query->where('total_archivos', 0);
    }

    public function scopeVencidas(Builder $query): Builder
    {
        return $query->where('fecha_vencimiento', '<', CalendarioHelper::hoy()->toDateString())
            ->whereIn('estado_tramite', ['Pendiente', 'En Tramite']);
    }

    public function scopeProximasAVencer(Builder $query, int $dias = 5): Builder
    {
        $fechaLimite = CalendarioHelper::hoy()->addDays($dias)->toDateString();

        return $query->where('fecha_vencimiento', '<=', $fechaLimite)
            ->where('fecha_vencimiento', '>=', CalendarioHelper::hoy()->toDateString())
            ->whereIn('estado_tramite', ['Pendiente', 'En Tramite']);
    }
}
