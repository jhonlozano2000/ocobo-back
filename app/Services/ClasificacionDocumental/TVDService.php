<?php

namespace App\Services\ClasificacionDocumental;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTVD;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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

        // Todo-o-nada: sin transacción, una excepción a mitad de archivo dejaba
        // la TVD importada a medias en la base de datos.
        return DB::transaction(fn () => $this->importarFilas($data, $dependencia->id));
    }

    /**
     * Recorre las filas de la hoja y crea Series/SubSeries.
     * Los errores por fila se acumulan y esa fila se omite; solo un fallo
     * duro aborta y revierte toda la importación.
     *
     * @param  array<int, array<int, mixed>>  $data
     * @return array{inserted: int, errors: array<int, string>}
     */
    private function importarFilas(array $data, int $dependenciaId): array
    {
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

            $gestion = $tipo === 'SerieDocumental' ? $this->parseInt($row[7] ?? null) : null;
            $central = $tipo === 'SerieDocumental' ? $this->parseInt($row[8] ?? null) : null;

            $elemento = ClasificacionDocumentalTVD::create([
                'tipo' => $tipo,
                'cod' => $codigo,
                'nom' => $nombre,
                'parent' => $parent,
                'dependencia_id' => $dependenciaId,
                'gestion' => $gestion,
                'central' => $central,
                'total_anios' => ($gestion !== null && $central !== null) ? $gestion + $central : $gestion,
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

        $codigoDependencia = trim((string) ($sheet->getCell('B4')->getValue() ?? ''));

        $errores = [];
        $dependencia = null;

        // B4 es el modo de fallo real del import: si la dependencia no existe,
        // la importación completa se aborta. Validarlo aqui evita sorpresas.
        if ($codigoDependencia === '') {
            $errores[] = 'Celda B4: falta el codigo de la dependencia.';
        } else {
            $dependencia = CalidadOrganigrama::where('cod_organico', $codigoDependencia)
                ->where('tipo', 'Dependencia')
                ->first();

            if (! $dependencia) {
                $errores[] = 'No se encontro la dependencia con codigo: '.$codigoDependencia;
            }
        }

        $filasValidas = 0;
        $series = 0;
        $subseries = 0;
        $tieneSeriePadre = false;

        foreach ($data as $index => $row) {
            if ($index < 6) {
                continue;
            }

            $colA = trim($row[0] ?? '');
            $colB = trim($row[1] ?? '');
            $colC = trim($row[2] ?? '');
            $nombre = trim($row[4] ?? '');

            if ($nombre === '') {
                continue;
            }

            $hasA = ! empty($colA);
            $hasB = ! empty($colB);
            $hasC = ! empty($colC);

            // Mismas ramas que importarFilas(): asi el diagnostico coincide
            // con lo que despues se va a escribir.
            if ($hasA && $hasB && ! $hasC) {
                $series++;
                $filasValidas++;
                $tieneSeriePadre = true;

                continue;
            }

            if (($hasA && $hasB && $hasC) || (! $hasA && ! $hasB && ! $hasC)) {
                if (! $tieneSeriePadre) {
                    $errores[] = 'Fila '.($index + 1).': SubSerie sin Serie padre.';

                    continue;
                }

                if ($hasA && $hasB && $hasC && trim($colC) === '') {
                    $errores[] = 'Fila '.($index + 1).': la SubSerie no tiene codigo.';

                    continue;
                }

                $subseries++;
                $filasValidas++;

                continue;
            }

            $errores[] = 'Fila '.($index + 1).': combinacion de columnas no reconocida (A/B/C).';
        }

        if ($filasValidas === 0) {
            $errores[] = 'El archivo no contiene filas de datos a partir de la fila 7.';
        }

        return [
            'filas_validas' => $filasValidas,
            'series' => $series,
            'subseries' => $subseries,
            'dependencia' => $dependencia ? $dependencia->nom_organico : null,
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
