<?php

namespace App\Services\Calidad;

use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CalidadOrganigramaService
{
    private const ICONOS = [
        'Dependencia' => ['nombre' => 'folder', 'color' => '#FFA500', 'clase' => 'fa-folder'],
        'Oficina' => ['nombre' => 'folder-open', 'color' => '#4CAF50', 'clase' => 'fa-folder-open'],
        'Cargo' => ['nombre' => 'user', 'color' => '#2196F3', 'clase' => 'fa-user']
    ];

    /**
     * Obtiene el organigrama completo.
     */
    public function getAll(array $filters = []): array|LengthAwarePaginator|Collection
    {
        // Obtener solo nodos raíz (sin padre) para construir el árbol desde la raíz
        $query = CalidadOrganigrama::whereNull('parent')->with('children');

        if (!empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        if (!empty($filters['search'])) {
            $query->where(fn($q) => $q
                ->where('nom_organico', 'like', "%{$filters['search']}%")
                ->orWhere('cod_organico', 'like', "%{$filters['search']}%")
            );
        }

        $query->orderBy('nom_organico', 'asc');

        $result = !empty($filters['per_page'])
            ? $query->paginate($filters['per_page'])
            : $query->get();

        return $this->formatAsTree($result);
    }

    /**
     * Obtiene solo dependencias.
     */
    public function getDependencias(array $filters = []): array|LengthAwarePaginator|Collection
    {
        $query = CalidadOrganigrama::dependenciasRaiz()->with('children');

        if (!empty($filters['search'])) {
            $query->where('nom_organico', 'like', "%{$filters['search']}%");
        }

        $query->orderBy('nom_organico', 'asc');

        $result = !empty($filters['per_page'])
            ? $query->paginate($filters['per_page'])
            : $query->get();

        return $this->formatAsTree($result);
    }

    /**
     * Obtiene solo oficinas.
     */
    public function getOficinas(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = CalidadOrganigrama::where('tipo', 'Oficina')->with('childrenCargos');

        if (!empty($filters['search'])) {
            $query->where('nom_organico', 'like', "%{$filters['search']}%");
        }

        $query->orderBy('nom_organico', 'asc');

        return !empty($filters['per_page'])
            ? $query->paginate($filters['per_page'])
            : $query->get();
    }

    /**
     * Obtiene estadísticas.
     */
    public function getStats(): array
    {
        $total = CalidadOrganigrama::count();
        
        return [
            'total_elementos' => $total,
            'total_dependencias' => CalidadOrganigrama::where('tipo', 'Dependencia')->count(),
            'total_oficinas' => CalidadOrganigrama::where('tipo', 'Oficina')->count(),
            'total_cargos' => CalidadOrganigrama::where('tipo', 'Cargo')->count(),
            'dependencias_raiz' => CalidadOrganigrama::dependenciasRaiz()->count(),
            'oficinas_sin_cargos' => CalidadOrganigrama::where('tipo', 'Oficina')->whereDoesntHave('childrenCargos')->count(),
            'cargos_sin_oficina' => CalidadOrganigrama::where('tipo', 'Cargo')->whereNull('parent')->count(),
            'elementos_recientes' => CalidadOrganigrama::orderByDesc('created_at')->limit(10)->get(['id', 'tipo', 'nom_organico', 'cod_organico', 'created_at'])
        ];
    }

    /**
     * Crea un nuevo nodo.
     */
    public function create(array $data): CalidadOrganigrama
    {
        return CalidadOrganigrama::create($data);
    }

    /**
     * Actualiza un nodo.
     */
    public function update(int $id, array $data): CalidadOrganigrama
    {
        $model = CalidadOrganigrama::findOrFail($id);
        $model->fill(array_intersect_key($data, array_flip($model->getFillable())));
        $model->save();
        return $model->fresh(['children']);
    }

    /**
     * Elimina un nodo si no tiene hijos.
     */
    public function delete(int $id): bool
    {
        $model = CalidadOrganigrama::findOrFail($id);

        if ($model->children()->count() > 0) {
            return false;
        }

        return $model->delete();
    }

    /**
     * Formatea como árbol.
     */
    public function formatAsTree($items, int $nivel = 0): array
    {
        $resultado = [];

        foreach ($items as $item) {
            $nodo = [
                'id' => $item->id,
                'tipo' => $item->tipo,
                'nom_organico' => $item->nom_organico,
                'cod_organico' => $item->cod_organico,
                'observaciones' => $item->observaciones,
                'parent' => $item->parent,
                'nivel' => $nivel,
                'icono' => self::ICONOS[$item->tipo] ?? self::ICONOS['Dependencia'],
                'expandido' => true,
                'tieneHijos' => $item->children && $item->children->count() > 0,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];

            if ($item->children && $item->children->count() > 0) {
                $nodo['children'] = $this->formatAsTree($item->children, $nivel + 1);
            }

            $resultado[] = $nodo;
        }

        return $resultado;
    }
}