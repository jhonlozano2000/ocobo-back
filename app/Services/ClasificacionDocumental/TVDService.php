<?php

namespace App\Services\ClasificacionDocumental;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTVD;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TVDService
{
    /**
     * Obtiene TVD con filtros.
     */
    public function getAll(array $filters = []): LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
    {
        $query = ClasificacionDocumentalTVD::with(['children', 'dependencia'])
            ->whereNull('parent');

        if (!empty($filters['dependencia_id'])) {
            $query->where('dependencia_id', $filters['dependencia_id']);
        }

        if (!empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        if (!empty($filters['buscar'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('cod', 'like', "%{$filters['buscar']}%")
                  ->orWhere('nom', 'like', "%{$filters['buscar']}%");
            });
        }

        $query->orderBy('cod', 'asc');

        return $query->get();
    }

    /**
     * Obtiene estadísticas totales.
     */
    public function getTotalStats(): array
    {
        return [
            'total_elementos' => ClasificacionDocumentalTVD::count(),
            'total_series' => ClasificacionDocumentalTVD::where('tipo', 'SerieDocumental')->count(),
            'total_subseries' => ClasificacionDocumentalTVD::where('tipo', 'SubSerieDocumental')->count(),
            'total_dependencias' => ClasificacionDocumentalTVD::distinct('dependencia_id')->count(),
        ];
    }

    /**
     * Obtiene estadísticas por dependencia.
     */
    public function getStatsByDependencia(int $id): array
    {
        $stats = ClasificacionDocumentalTVD::where('dependencia_id', $id)
            ->selectRaw('
                COUNT(*) as total_elementos,
                SUM(CASE WHEN tipo = "SerieDocumental" THEN 1 ELSE 0 END) as series,
                SUM(CASE WHEN tipo = "SubSerieDocumental" THEN 1 ELSE 0 END) as subseries
            ')
            ->first();

        return [
            'total_elementos' => $stats->total_elementos,
            'series' => $stats->series,
            'subseries' => $stats->subseries,
        ];
    }

    /**
     * Crea un elemento TVD.
     */
    public function create(array $data): ClasificacionDocumentalTVD
    {
        $data['user_register'] = auth()->id();
        return ClasificacionDocumentalTVD::create($data);
    }

    /**
     * Actualiza un elemento TVD.
     */
    public function update(int $id, array $data): ?ClasificacionDocumentalTVD
    {
        $tvd = ClasificacionDocumentalTVD::find($id);
        
        if (!$tvd) {
            return null;
        }

        $tvd->update($data);
        return $tvd;
    }

    /**
     * Elimina un elemento TVD si no tiene hijos.
     */
    public function delete(int $id): bool
    {
        $tvd = ClasificacionDocumentalTVD::find($id);
        
        if (!$tvd) {
            return false;
        }

        if ($tvd->hasChildren()) {
            return false;
        }

        return $tvd->delete();
    }
}