<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\ConfigDiviPoli;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConfigDiviPoliService
{
    private const TIPOS_VALIDOS = ['Pais', 'Departamento', 'Municipio'];
    private const CACHE_TTL = 15;

    public function getAll(array $filters = []): LengthAwarePaginator|Collection
    {
        $cacheKey = 'divi_poli:all:' . md5(json_encode($filters ?? []));

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL), function () use ($filters) {
            $query = ConfigDiviPoli::select([
                'config_divi_poli.id',
                'config_divi_poli.parent',
                'config_divi_poli.codigo',
                'config_divi_poli.nombre',
                'config_divi_poli.tipo',
                DB::raw("CASE
                    WHEN config_divi_poli.tipo = 'Pais' THEN config_divi_poli.codigo
                    WHEN config_divi_poli.tipo = 'Departamento' THEN pais_directo.codigo
                    WHEN config_divi_poli.tipo = 'Municipio' THEN pais.codigo
                    ELSE NULL
                END as pais_codigo"),
                DB::raw("CASE
                    WHEN config_divi_poli.tipo = 'Pais' THEN config_divi_poli.nombre
                    WHEN config_divi_poli.tipo = 'Departamento' THEN pais_directo.nombre
                    WHEN config_divi_poli.tipo = 'Municipio' THEN pais.nombre
                    ELSE NULL
                END as pais_nombre")
            ])
            ->leftJoin('config_divi_poli as departamento', function($j) {
                $j->on('config_divi_poli.parent', '=', 'departamento.id')
                  ->where('departamento.tipo', '=', 'Departamento');
            })
            ->leftJoin('config_divi_poli as pais', function($j) {
                $j->on('departamento.parent', '=', 'pais.id')
                  ->where('pais.tipo', '=', 'Pais');
            })
            ->leftJoin('config_divi_poli as pais_directo', function($j) {
                $j->on('config_divi_poli.parent', '=', 'pais_directo.id')
                  ->where('pais_directo.tipo', '=', 'Pais');
            });

            $this->applyFilters($query, $filters);

            $query->orderByRaw("CASE
                WHEN config_divi_poli.tipo = 'Pais' THEN 1
                WHEN config_divi_poli.tipo = 'Departamento' THEN 2
                WHEN config_divi_poli.tipo = 'Municipio' THEN 3
                ELSE 4
            END")
            ->orderBy('pais_nombre', 'asc')
            ->orderBy('config_divi_poli.nombre', 'asc');

            return $this->paginateIfRequested($query, $filters['per_page'] ?? null);
        });
    }

    public function getHierarchy(): Collection
    {
        return ConfigDiviPoli::where('tipo', 'Pais')
            ->with(['children' => function($q) {
                $q->where('tipo', 'Departamento')
                  ->orderBy('nombre', 'asc')
                  ->with(['children' => function($sq) {
                      $sq->where('tipo', 'Municipio')->orderBy('nombre', 'asc');
                  }]);
            }])
            ->orderBy('nombre', 'asc')
            ->get()
            ->map(function($p) {
                return $this->mapHierarchy($p);
            });
    }

    public function getPaises(): Collection
    {
        return ConfigDiviPoli::where('tipo', 'Pais')->orderBy('nombre', 'asc')->get();
    }

    public function getDepartamentos(int $paisId): Collection
    {
        return ConfigDiviPoli::where('parent', $paisId)
            ->where('tipo', 'Departamento')
            ->orderBy('nombre', 'asc')
            ->get();
    }

    public function getMunicipios(int $departamentoId): Collection
    {
        return ConfigDiviPoli::where('parent', $departamentoId)
            ->where('tipo', 'Municipio')
            ->orderBy('nombre', 'asc')
            ->get();
    }

    public function getStats(): array
    {
        $total = ConfigDiviPoli::count();
        $conteoPorTipo = ConfigDiviPoli::selectRaw('tipo, COUNT(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo')
            ->toArray();

        return [
            'total_divisiones' => $total,
            'conteo_por_tipo' => $conteoPorTipo
        ];
    }

    public function create(array $data): ConfigDiviPoli
    {
        $model = ConfigDiviPoli::create($data);
        $this->clearCache();
        return $model;
    }

    public function update(int $id, array $data): ConfigDiviPoli
    {
        $model = ConfigDiviPoli::findOrFail($id);
        $model->fill($data)->save();
        $this->clearCache();
        return $model;
    }

    public function delete(int $id): bool
    {
        $model = ConfigDiviPoli::findOrFail($id);

        if ($model->children()->exists()) {
            return false;
        }

        $deleted = $model->delete();
        $this->clearCache();
        return $deleted;
    }

    public function isTipoValido(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS_VALIDOS);
    }

    private function clearCache(): void
    {
        Cache::forget('divi_poli:all:' . md5(json_encode([])));
        Cache::forget('divi_poli:hierarchy');
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['tipo'])) {
            $query->where('config_divi_poli.tipo', $filters['tipo']);
        }

        if (!empty($filters['parent'])) {
            $query->where('config_divi_poli.parent', $filters['parent']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('config_divi_poli.nombre', 'like', "%{$search}%")
                  ->orWhere('config_divi_poli.codigo', 'like', "%{$search}%");
            });
        }
    }

    private function mapHierarchy($pais): array
    {
        return [
            'id' => $pais->id,
            'codigo' => $pais->codigo,
            'nombre' => $pais->nombre,
            'tipo' => $pais->tipo,
            'departamentos' => $pais->children->map(function($d) {
                return [
                    'id' => $d->id,
                    'codigo' => $d->codigo,
                    'nombre' => $d->nombre,
                    'tipo' => $d->tipo,
                    'municipios' => $d->children->map(function($m) {
                        return [
                            'id' => $m->id,
                            'codigo' => $m->codigo,
                            'nombre' => $m->nombre,
                            'tipo' => $m->tipo
                        ];
                    })
                ];
            })
        ];
    }

    /**
     * Obtiene una división política con todos sus ancestros.
     */
    public function getWithAncestors(int $id): ?array
    {
        $item = ConfigDiviPoli::find($id);
        
        if (!$item) {
            return null;
        }

        // Obtener ancestros recursivamente
        $ancestors = [];
        $current = $item;
        
        while ($current->parent) {
            $parent = ConfigDiviPoli::find($current->parent);
            if ($parent) {
                $ancestors[] = [
                    'id' => $parent->id,
                    'codigo' => $parent->codigo,
                    'nombre' => $parent->nombre,
                    'tipo' => $parent->tipo
                ];
                $current = $parent;
            } else {
                break;
            }
        }

        return [
            'id' => $item->id,
            'codigo' => $item->codigo,
            'nombre' => $item->nombre,
            'tipo' => $item->tipo,
            'ancestors' => $ancestors
        ];
    }

    private function paginateIfRequested($query, ?int $perPage): LengthAwarePaginator|Collection
    {
        return $perPage ? $query->paginate($perPage) : $query->get();
    }
}