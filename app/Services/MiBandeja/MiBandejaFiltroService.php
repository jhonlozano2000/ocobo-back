<?php

namespace App\Services\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ControlAcceso\UserCargo;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de filtro jerárquico para Mi Bandeja.
 *
 * Determina qué radicados puede ver un usuario según su nivel:
 * - mis_radicados: solo los radicados donde el usuario es responsable
 * - ver_todo (jefe dependencia): todos los radicados de su dependencia
 * - ver_todo (jefe oficina): todos los radicados de su oficina
 */
class MiBandejaFiltroService
{
    /**
     * Obtener la dependencia raíz del usuario (tipo 'Dependencia' con parent NULL).
     */
    public static function getDependenciaDelUsuario(int $userId): ?CalidadOrganigrama
    {
        $cargo = UserCargo::activos()
            ->delUsuario($userId)
            ->with('cargo')
            ->first();

        if (! $cargo || ! $cargo->cargo) {
            return null;
        }

        $jerarquia = $cargo->cargo->getJerarquiaCompleta();

        // Buscar el nodo de tipo 'Dependencia' en la jerarquía
        foreach ($jerarquia as $nodo) {
            if ($nodo['tipo'] === 'Dependencia') {
                return CalidadOrganigrama::find($nodo['id']);
            }
        }

        return null;
    }

    /**
     * Obtener la oficina del usuario (padre directo del cargo, tipo 'Oficina').
     */
    public static function getOficinaDelUsuario(int $userId): ?CalidadOrganigrama
    {
        $cargo = UserCargo::activos()
            ->delUsuario($userId)
            ->with('cargo')
            ->first();

        if (! $cargo || ! $cargo->cargo) {
            return null;
        }

        $parentId = $cargo->cargo->parent;

        if ($parentId) {
            $parent = CalidadOrganigrama::find($parentId);
            if ($parent && $parent->tipo === 'Oficina') {
                return $parent;
            }
        }

        return null;
    }

    /**
     * Obtener todos los user_ids dentro de una dependencia (recursivo).
     * Busca todos los nodos Cargo hijos y nietos de la dependencia.
     */
    public static function getUsuariosDeLaDependencia(int $dependenciaId): array
    {
        // Obtener todos los IDs de nodos bajo esta dependencia
        $nodoIds = self::getDescendantIds($dependenciaId);

        // Agregar el mismo nodo raíz
        $nodoIds[] = $dependenciaId;

        // Buscar todos los UserCargo activos que apunten a esos nodos
        $userCargos = UserCargo::activos()
            ->whereIn('cargo_id', $nodoIds)
            ->pluck('user_id')
            ->unique()
            ->toArray();

        return $userCargos;
    }

    /**
     * Obtener todos los user_ids dentro de una oficina.
     * La oficina contiene nodos Cargo directamente.
     */
    public static function getUsuariosDeLaOficina(int $oficinaId): array
    {
        $userCargos = UserCargo::activos()
            ->whereHas('cargo', function ($q) use ($oficinaId) {
                $q->where('parent', $oficinaId);
            })
            ->pluck('user_id')
            ->unique()
            ->toArray();

        return $userCargos;
    }

    /**
     * Determinar el nivel de acceso del usuario.
     * Retorna 'dependencia', 'oficina', o null (usuario normal).
     */
    public static function getNivelDelUsuario(int $userId): ?string
    {
        $user = \App\Models\User::find($userId);

        if (! $user) {
            return null;
        }

        if ($user->hasPermissionTo('Mi Bandeja -> Jefe de dependencia')) {
            return 'dependencia';
        }

        if ($user->hasPermissionTo('Mi Bandeja -> Jefe de oficina')) {
            return 'oficina';
        }

        return null;
    }

    /**
     * Obtener los user_ids según el nivel del usuario.
     * Retorna null si es usuario normal (solo ve los suyos).
     */
    public static function getUserIdsParaFiltro(int $userId): ?array
    {
        $nivel = self::getNivelDelUsuario($userId);

        if ($nivel === 'dependencia') {
            $dependencia = self::getDependenciaDelUsuario($userId);
            if ($dependencia) {
                return self::getUsuariosDeLaDependencia($dependencia->id);
            }
        }

        if ($nivel === 'oficina') {
            $oficina = self::getOficinaDelUsuario($userId);
            if ($oficina) {
                return self::getUsuariosDeLaOficina($oficina->id);
            }
        }

        return null;
    }

    /**
     * Obtener todos los IDs de nodos hijos recursivamente.
     */
    protected static function getDescendantIds(int $parentId): array
    {
        $hijos = CalidadOrganigrama::where('parent', $parentId)->pluck('id')->toArray();
        $todos = $hijos;

        foreach ($hijos as $hijoId) {
            $todos = array_merge($todos, self::getDescendantIds($hijoId));
        }

        return $todos;
    }
}
