<?php

namespace App\Services\ClasificacionDocumental;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTVD;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TVDService
{
    /**
     * Obtiene TVD con filtros.
     */
    public function getAll(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = ClasificacionDocumentalTVD::with(['children', 'dependencia'])
            ->whereNull('parent');

        if (! empty($filters['dependencia_id'])) {
            $query->where('dependencia_id', $filters['dependencia_id']);
        }

        if (! empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        if (! empty($filters['buscar'])) {
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

        if (! $tvd) {
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

        if (! $tvd) {
            return false;
        }

        if ($tvd->hasChildren()) {
            return false;
        }

        return $tvd->delete();
    }

    /**
     * Importa TVD desde un archivo Excel/CSV.
     * Estructura esperada (similar a la plantilla TRD):
     * Fila 4: A=Dependencia, B=codigo dependencia
     * Datos desde fila 7: A=dependencia, B=serie, C=subserie, E=nombre; H=gestion, I=central
     */
    public function importFromExcel(UploadedFile $archivo): array
    {
        $spreadsheet = IOFactory::load($archivo->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        $codigoDependencia = trim($sheet->getCell('B4')->getValue() ?? '');
        $dependencia = CalidadOrganigrama::where('cod_organico', $codigoDependencia)
            ->where('tipo', 'Dependencia')
            ->first();

        if (! $dependencia) {
            return ['inserted' => 0, 'errors' => ['No se encontró la dependencia con código: '.$codigoDependencia]];
        }

        $idSerie = null;
        $inserted = 0;
        $errors = [];

        foreach ($data as $index => $row) {
            if ($index < 6) {
                continue;
            }

            $colA = trim($row[0] ?? '');
            $colB = trim($row[1] ?? '');
            $colC = trim($row[2] ?? '');
            $nombre = trim($row[4] ?? '');

            if (empty($nombre)) {
                continue;
            }

            $hasA = ! empty($colA);
            $hasB = ! empty($colB);
            $hasC = ! empty($colC);

            $tipo = null;
            $codigo = null;
            $parent = null;

            if ($hasA && $hasB && ! $hasC) {
                $tipo = 'SerieDocumental';
                $codigo = $colB;
                $parent = null;
            } elseif ($hasA && $hasB && $hasC) {
                $tipo = 'SubSerieDocumental';
                $codigo = $colC;
                if ($idSerie === null) {
                    $errors[] = 'Fila '.($index + 1).': SubSerie sin Serie padre';

                    continue;
                }
                $parent = $idSerie;
            } elseif (! $hasA && ! $hasB && ! $hasC) {
                $tipo = 'SubSerieDocumental';
                $codigo = null;
                if ($idSerie === null) {
                    $errors[] = 'Fila '.($index + 1).': elemento sin Serie padre';

                    continue;
                }
                $parent = $idSerie;
            } else {
                continue;
            }

            $elemento = ClasificacionDocumentalTVD::create([
                'tipo' => $tipo,
                'cod' => $codigo,
                'nom' => $nombre,
                'parent' => $parent,
                'dependencia_id' => $dependencia->id,
                'gestion' => $tipo === 'SerieDocumental' ? $this->parseInt($row[7] ?? null) : null,
                'central' => $tipo === 'SerieDocumental' ? $this->parseInt($row[8] ?? null) : null,
                'soporte' => trim($row[9] ?? '') ?: null,
                'disposicion_final' => trim($row[10] ?? '') ?: null,
                'estado' => true,
                'user_register' => auth()->id(),
            ]);

            $inserted++;

            if ($tipo === 'SerieDocumental') {
                $idSerie = $elemento->id;
            }
        }

        return [
            'inserted' => $inserted,
            'errors' => $errors,
        ];
    }

    /**
     * Valida un archivo TVD sin importar, retornando filas válidas/errores.
     */
    public function validarArchivo(UploadedFile $archivo): array
    {
        $spreadsheet = IOFactory::load($archivo->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        $filasValidas = 0;
        $errores = [];

        foreach ($data as $index => $row) {
            if ($index < 6) {
                continue;
            }

            $nombre = trim($row[4] ?? '');

            if (empty($nombre)) {
                continue;
            }

            $filasValidas++;

            if (empty(trim($row[1] ?? '')) && empty(trim($row[2] ?? ''))) {
                $errores[] = 'Fila '.($index + 1).': elemento sin clasificación de serie/subserie';
            }
        }

        return [
            'filas_validas' => $filasValidas,
            'errores' => $errores,
        ];
    }

    private function parseInt($value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (preg_match('/\d+/', (string) $value, $m)) {
            return (int) $m[0];
        }

        return null;
    }
}
