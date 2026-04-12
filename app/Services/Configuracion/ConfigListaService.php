<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\ConfigLista;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ConfigListaService
{
    private const CACHE_TTL = 10; // minutos

    /**
     * Obtiene todas las listas con filtros aplicados.
     */
    public function getAll(array $filters = []): LengthAwarePaginator|Collection
    {
        $cacheKey = 'config_listas:all:' . md5(json_encode($filters ?? []));

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL), function () use ($filters) {
            $query = ConfigLista::with('detalles');

            if (!empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('cod', 'like', "%{$filters['search']}%")
                        ->orWhere('nombre', 'like', "%{$filters['search']}%");
                });
            }

            $query->orderBy('cod', 'asc');

            return $this->paginateIfRequested($query, $filters['per_page'] ?? null);
        });
    }

    /**
     * Obtiene solo listas maestras (sin detalles).
     */
    public function getOnlyHeads(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = ConfigLista::query();

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('cod', 'like', "%{$filters['search']}%")
                    ->orWhere('nombre', 'like', "%{$filters['search']}%");
            });
        }

        $query->orderBy('cod', 'asc');

        return $this->paginateIfRequested($query, $filters['per_page'] ?? null);
    }

    /**
     * Obtiene una lista con sus detalles activos.
     */
    public function getWithActiveDetails(int $id): ?ConfigLista
    {
        return ConfigLista::with(['detalles' => fn($q) => $q->where('estado', true)])
            ->where('id', $id)
            ->where('estado', true)
            ->first();
    }

    /**
     * Crea una nueva lista.
     */
    public function create(array $data): ConfigLista
    {
        $lista = ConfigLista::create($data);
        $this->clearCache();
        return $lista;
    }

    /**
     * Actualiza una lista existente.
     */
    public function update(int $id, array $data): ?ConfigLista
    {
        $lista = ConfigLista::find($id);
        
        if (!$lista) {
            return null;
        }

        $lista->update($data);
        $this->clearCache();
        return $lista->fresh(['detalles']);
    }

    /**
     * Elimina una lista si no tiene detalles asociados.
     */
    public function delete(int $id): bool
    {
        $lista = ConfigLista::find($id);
        
        if (!$lista) {
            return false;
        }

        if ($lista->detalles()->exists()) {
            return false;
        }

        $deleted = $lista->delete();
        $this->clearCache();
        return $deleted;
    }

    /**
     * Limpia el cache de listas.
     */
    private function clearCache(): void
    {
        Cache::forget('config_listas:all:' . md5(json_encode([])));
        Cache::forget('config_listas:heads:' . md5(json_encode([])));
    }

    /**
     * Aplica paginación si se solicita.
     */
    private function paginateIfRequested($query, ?int $perPage): LengthAwarePaginator|Collection
    {
        return $perPage ? $query->paginate($perPage) : $query->get();
    }
}