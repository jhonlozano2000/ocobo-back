<?php

namespace App\Services\Metricas;

use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Support\Facades\DB;

/**
 * Dependencia (nodo tipo Dependencia más cercano hacia la raíz) del cargo activo de un usuario.
 * Guarda en memoria lo ya resuelto, para que el backfill no repita consultas por cada archivo.
 */
class DependenciaDeUsuario
{
    /** @var array<int, array{id: int, nombre: string}|null> */
    private array $porUsuario = [];

    /** @var array<int, array{id: int, nombre: string}|null> */
    private array $porNodo = [];

    /**
     * @return array{id: int, nombre: string}|null
     */
    public function resolver(?int $userId): ?array
    {
        if (! $userId) {
            return null;
        }

        if (! array_key_exists($userId, $this->porUsuario)) {
            $cargoId = DB::table('users_cargos')
                ->where('user_id', $userId)
                ->where('estado', true)
                ->whereNull('fecha_fin')
                ->orderByDesc('fecha_inicio')
                ->value('cargo_id');

            $this->porUsuario[$userId] = $cargoId ? $this->dependenciaDeNodo((int) $cargoId) : null;
        }

        return $this->porUsuario[$userId];
    }

    /**
     * @return array{id: int, nombre: string}|null
     */
    private function dependenciaDeNodo(int $nodoId): ?array
    {
        if (array_key_exists($nodoId, $this->porNodo)) {
            return $this->porNodo[$nodoId];
        }

        $actual = CalidadOrganigrama::query()->find($nodoId, ['id', 'tipo', 'nom_organico', 'parent']);
        $visitados = [];
        while ($actual && $actual->tipo !== 'Dependencia' && ! isset($visitados[$actual->id])) {
            $visitados[$actual->id] = true;
            $actual = $actual->parent
                ? CalidadOrganigrama::query()->find($actual->parent, ['id', 'tipo', 'nom_organico', 'parent'])
                : null;
        }

        return $this->porNodo[$nodoId] = $actual
            ? ['id' => (int) $actual->id, 'nombre' => (string) $actual->nom_organico]
            : null;
    }
}
