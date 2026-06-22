<?php

namespace App\Traits;

use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Trait AbacHierarquico
 *
 * Implementa Attribute-Based Access Control (ABAC) con filtrado por jerarquía organizacional.
 *
 * Uso:
 * - Agregar `use AbacHierarquico;` al modelo
 * - Definir constante ABAC_USER_COLUMN = 'usuario_crea' (columna del usuario creador)
 * - Definir constante ABAC_RESPONSABLES_RELATION = 'responsables' (relación de responsables)
 * - Usar scopes: paraUsuario(), paraMiDependencia(), paraMisSubordinados()
 */
trait AbacHierarquico
{
    /**
     * Scope para filtrar registros visibles para un usuario específico.
     *
     * Reglas de visibilidad:
     * 1. Registros creados por el usuario
     * 2. Registros donde el usuario es responsable
     * 3. Registros de la misma dependencia del usuario
     * 4. Registros de dependencias subordinadas (si el usuario tiene permiso)
     *
     * @param  Builder  $query
     * @param  User|int|null  $user  Usuario o ID (null = usuario autenticado)
     * @return Builder
     */
    public function scopeParaUsuario($query, $user = null)
    {
        $user = $this->resolverUsuario($user);

        if (! $user) {
            return $query->whereRaw('1 = 0'); // Sin acceso si no hay usuario
        }

        return $query->where(function ($q) use ($user) {
            // 1. Registros creados por el usuario
            if (defined('static::ABAC_USER_COLUMN')) {
                $q->orWhere(static::ABAC_USER_COLUMN, $user->id);
            }

            // 2. Registros donde el usuario es responsable
            if (defined('static::ABAC_RESPONSABLES_RELATION')) {
                $q->orWhereHas(static::ABAC_RESPONSABLES_RELATION, function ($subQ) use ($user) {
                    $subQ->whereHas('userCargo', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
                });
            }

            // 3. Registros de la misma dependencia
            $codigosDependencia = $this->obtenerCodigosDependencia($user);
            if (! empty($codigosDependencia)) {
                $q->orWhereHas('usuarioCreaRadicado.cargoActivo', function ($subQ) use ($codigosDependencia) {
                    $subQ->whereIn('cod_organico', $codigosDependencia);
                });
            }
        });
    }

    /**
     * Scope para filtrar registros de la misma dependencia del usuario.
     *
     * @param  Builder  $query
     * @param  User|int|null  $user
     * @return Builder
     */
    public function scopeParaMiDependencia($query, $user = null)
    {
        $user = $this->resolverUsuario($user);

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $codigosDependencia = $this->obtenerCodigosDependencia($user);

        if (empty($codigosDependencia)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('usuarioCreaRadicado.cargoActivo', function ($q) use ($codigosDependencia) {
            $q->whereIn('cod_organico', $codigosDependencia);
        });
    }

    /**
     * Scope para filtrar registros de dependencias subordinadas.
     *
     * @param  Builder  $query
     * @param  User|int|null  $user
     * @return Builder
     */
    public function scopeParaMisSubordinados($query, $user = null)
    {
        $user = $this->resolverUsuario($user);

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $codigosSubordinados = $this->obtenerCodigosSubordinados($user);

        if (empty($codigosSubordinados)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('usuarioCreaRadicado.cargoActivo', function ($q) use ($codigosSubordinados) {
            $q->whereIn('cod_organico', $codigosSubordinados);
        });
    }

    /**
     * Scope para filtrar con permiso jerárquico completo.
     *
     * @param  Builder  $query
     * @param  User|int|null  $user
     * @return Builder
     */
    public function scopeConPermisoJerarquico($query, $user = null)
    {
        $user = $this->resolverUsuario($user);

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Si el usuario tiene permiso de ver todo, no filtrar
        try {
            if ($user->hasPermissionTo('Radicar -> Ver Todos')) {
                return $query;
            }
        } catch (PermissionDoesNotExist $e) {
            Log::debug('Permiso Radicar -> Ver Todos no existe, aplicando filtrado jerárquico', [
                'user_id' => $user->id,
            ]);
        }

        // Obtener todos los códigos de la jerarquía (propia + subordinados)
        $codigosJerarquia = $this->obtenerCodigosJerarquiaCompleta($user);

        if (empty($codigosJerarquia)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($user, $codigosJerarquia) {
            // 1. Registros creados por el usuario
            if (defined('static::ABAC_USER_COLUMN')) {
                $q->orWhere(static::ABAC_USER_COLUMN, $user->id);
            }

            // 2. Registros donde el usuario es responsable
            if (defined('static::ABAC_RESPONSABLES_RELATION')) {
                $q->orWhereHas(static::ABAC_RESPONSABLES_RELATION, function ($subQ) use ($user) {
                    $subQ->whereHas('userCargo', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
                });
            }

            // 3. Registros de la jerarquía completa
            $q->orWhereHas('usuarioCreaRadicado.cargoActivo', function ($subQ) use ($codigosJerarquia) {
                $subQ->whereIn('cod_organico', $codigosJerarquia);
            });
        });
    }

    /**
     * Obtiene los códigos de dependencia del usuario.
     *
     * @param  User  $user
     */
    protected function obtenerCodigosDependencia($user): array
    {
        $cargoActivo = UserCargo::cargoActivoDelUsuario($user->id);

        if (! $cargoActivo || ! $cargoActivo->cargo) {
            return [];
        }

        // Retornar el código del cargo actual y sus padres
        $codigos = [];
        $cargo = $cargoActivo->cargo;

        while ($cargo && is_object($cargo)) {
            $codigos[] = $cargo->cod_organico;
            $parent = $cargo->parent;
            $cargo = is_object($parent) ? $parent : null;
        }

        return array_unique($codigos);
    }

    /**
     * Obtiene los códigos de las dependencias subordinadas.
     *
     * @param  User  $user
     */
    protected function obtenerCodigosSubordinados($user): array
    {
        $cargoActivo = UserCargo::cargoActivoDelUsuario($user->id);

        if (! $cargoActivo || ! $cargoActivo->cargo) {
            return [];
        }

        // Obtener todos los hijos del cargo actual
        return $cargoActivo->cargo->getDescendientesCodigos();
    }

    /**
     * Obtiene todos los códigos de la jerarquía completa (propia + subordinados).
     *
     * @param  User  $user
     */
    protected function obtenerCodigosJerarquiaCompleta($user): array
    {
        $propios = $this->obtenerCodigosDependencia($user);
        $subordinados = $this->obtenerCodigosSubordinados($user);

        return array_unique(array_merge($propios, $subordinados));
    }

    /**
     * Resuelve el usuario a partir de un ID o objeto User.
     *
     * @param  User|int|null  $user
     * @return User|null
     */
    protected function resolverUsuario($user = null)
    {
        if ($user === null) {
            return Auth::user();
        }

        if (is_int($user)) {
            return User::find($user);
        }

        return $user;
    }
}
