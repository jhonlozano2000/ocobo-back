<?php

namespace App\Services\ClasificacionDocumental;

use App\Helpers\ExcelReadFilter;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTVD;
use App\Models\ClasificacionDocumental\FondoAcumulado;
use App\Models\ClasificacionDocumental\FondoAcumuladoPeriodo;
use App\Models\ClasificacionDocumental\FondoAcumuladoUnidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * TVD por fondo acumulado (M11-02): cada elemento pertenece a un período del fondo y a una unidad
 * administrativa histórica de ese período. La aprobación es por período.
 */
class TVDService
{
    /**
     * Obtiene los elementos raíz de la TVD con sus hijos.
     */
    public function getAll(array $filters = []): Collection
    {
        $query = ClasificacionDocumentalTVD::with(['children', 'unidad', 'periodo.fondo'])
            ->whereNull('parent');

        $this->aplicarFiltros($query, $filters);

        return $query->orderBy('cod', 'asc')->get();
    }

    /**
     * Filtros comunes del listado y la exportación.
     */
    public function aplicarFiltros(Builder $query, array $filters): Builder
    {
        if (! empty($filters['fondo_id'])) {
            $query->whereHas('periodo', fn ($q) => $q->where('fondo_id', $filters['fondo_id']));
        }

        if (! empty($filters['periodo_id'])) {
            $query->where('periodo_id', $filters['periodo_id']);
        }

        if (! empty($filters['unidad_id'])) {
            $query->where('unidad_id', $filters['unidad_id']);
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

        return $query;
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
            'total_fondos' => FondoAcumulado::count(),
            'total_periodos' => FondoAcumuladoPeriodo::count(),
            'periodos_aprobados' => FondoAcumuladoPeriodo::whereNotNull('fecha_aprobacion')->count(),
            'total_unidades' => ClasificacionDocumentalTVD::distinct('unidad_id')->count('unidad_id'),
        ];
    }

    /**
     * Crea un elemento TVD.
     */
    public function create(array $data): ClasificacionDocumentalTVD
    {
        return DB::transaction(function () use ($data) {
            $this->asegurarEditable($data['periodo_id'] ?? null);

            $data['user_register'] = auth()->id();

            return ClasificacionDocumentalTVD::create($data);
        });
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

        return DB::transaction(function () use ($tvd, $data) {
            $this->asegurarEditable($tvd->periodo_id);
            if (! empty($data['periodo_id']) && (int) $data['periodo_id'] !== (int) $tvd->periodo_id) {
                $this->asegurarEditable($data['periodo_id']);
            }

            $tvd->update($data);

            return $tvd;
        });
    }

    /**
     * Elimina un elemento TVD si no tiene hijos.
     */
    public function delete(int $id): bool
    {
        $tvd = ClasificacionDocumentalTVD::find($id);

        if (! $tvd || $tvd->hasChildren()) {
            return false;
        }

        return DB::transaction(function () use ($tvd) {
            $this->asegurarEditable($tvd->periodo_id);

            return $tvd->delete();
        });
    }

    /**
     * La TVD se aprueba por período (M11-02); aprobado, el período no admite cambios.
     */
    public function periodoAprobado(?int $periodoId): bool
    {
        return $periodoId && FondoAcumuladoPeriodo::whereKey($periodoId)->whereNotNull('fecha_aprobacion')->exists();
    }

    /**
     * Debe llamarse dentro de una transacción: bloquea el período para que no se apruebe a la par.
     */
    public function asegurarEditable(?int $periodoId): void
    {
        $periodo = $periodoId ? FondoAcumuladoPeriodo::whereKey($periodoId)->lockForUpdate()->first() : null;

        if ($periodo?->fecha_aprobacion !== null) {
            throw ValidationException::withMessages(['periodo_id' => 'La TVD de este período ya fue aprobada y no puede modificarse.']);
        }
    }

    /**
     * Importa la TVD de una unidad histórica desde la plantilla Excel/CSV.
     * Estructura esperada (similar a la plantilla TRD):
     * Fila 4: A=Unidad, B=código de la unidad histórica dentro del período elegido
     * Datos desde fila 7: A=unidad, B=serie, C=subserie, E=nombre; H=gestion, I=central, J=soporte, K=disposición
     */
    public function importFromExcel(UploadedFile $archivo, int $periodoId): array
    {
        $reader = IOFactory::createReaderForFile($archivo->getRealPath());
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new ExcelReadFilter(5000));
        $sheet = $reader->load($archivo->getRealPath())->getActiveSheet();
        $data = $sheet->toArray();
        $codigoUnidad = trim((string) ($sheet->getCell('B4')->getValue() ?? ''));

        // M11-05: Ejecutar validación previa del archivo antes de procesar
        $validacion = $this->validarArchivo($archivo, $periodoId);
        if (! empty($validacion['errores'])) {
            return ['inserted' => 0, 'errors' => $validacion['errores']];
        }

        // Todo-o-nada: sin transacción, una excepción a mitad de archivo dejaba
        // la TVD importada a medias en la base de datos.
        return DB::transaction(function () use ($data, $periodoId, $codigoUnidad) {
            $periodo = FondoAcumuladoPeriodo::whereKey($periodoId)->lockForUpdate()->first();

            if (! $periodo) {
                return ['inserted' => 0, 'errors' => ['El período seleccionado no existe.']];
            }

            if ($periodo->fecha_aprobacion !== null) {
                return ['inserted' => 0, 'errors' => ['La TVD del período '.$periodo->nombre.' ya fue aprobada y no puede modificarse.']];
            }

            $unidad = $this->unidadDelPeriodo($periodoId, $codigoUnidad);

            if (! $unidad) {
                return ['inserted' => 0, 'errors' => ['No se encontró la unidad con código "'.$codigoUnidad.'" en el período '.$periodo->nombre.'.']];
            }

            return $this->importarFilas($data, $periodo->id, $unidad->id);
        });
    }

    private function unidadDelPeriodo(int $periodoId, string $codigo): ?FondoAcumuladoUnidad
    {
        return $codigo === '' ? null : FondoAcumuladoUnidad::where('periodo_id', $periodoId)->where('codigo', $codigo)->first();
    }

    /**
     * Recorre las filas de la hoja y crea Series/SubSeries.
     * Los errores por fila se acumulan y esa fila se omite; solo un fallo
     * duro aborta y revierte toda la importación.
     *
     * @param  array<int, array<int, mixed>>  $data
     * @return array{inserted: int, errors: array<int, string>}
     */
    private function importarFilas(array $data, int $periodoId, int $unidadId): array
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

            if ($hasA && $hasB && ! $hasC) {
                $tipo = 'SerieDocumental';
                $codigo = $colB;
                $parent = null;

                // Reimportar la misma plantilla no debe duplicar series en la unidad
                if (ClasificacionDocumentalTVD::where('unidad_id', $unidadId)->where('cod', $codigo)->exists()) {
                    $errors[] = 'Fila '.($index + 1).': la serie '.$codigo.' ya existe en la unidad; se omiten la serie y sus subseries';
                    $idSerie = false;

                    continue;
                }
            } elseif (($hasA && $hasB && $hasC) || (! $hasA && ! $hasB && ! $hasC)) {
                $tipo = 'SubSerieDocumental';
                $codigo = $hasC ? $colC : null;

                if ($idSerie === false) {
                    continue;
                }
                if ($idSerie === null) {
                    $errors[] = 'Fila '.($index + 1).': SubSerie sin Serie padre';

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
                'periodo_id' => $periodoId,
                'unidad_id' => $unidadId,
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
    public function validarArchivo(UploadedFile $archivo, int $periodoId): array
    {
        $reader = IOFactory::createReaderForFile($archivo->getRealPath());
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new ExcelReadFilter(5000));
        $sheet = $reader->load($archivo->getRealPath())->getActiveSheet();
        $data = $sheet->toArray();

        $codigoUnidad = trim((string) ($sheet->getCell('B4')->getValue() ?? ''));
        $periodo = FondoAcumuladoPeriodo::find($periodoId);

        $errores = [];
        $unidad = null;

        if (! $periodo) {
            $errores[] = 'El período seleccionado no existe.';
        } elseif ($periodo->fecha_aprobacion !== null) {
            $errores[] = 'La TVD del período '.$periodo->nombre.' ya fue aprobada: no admite importaciones.';
        }

        // B4 es el modo de fallo real del import: si la unidad no existe en el período,
        // la importación completa se aborta. Validarlo aqui evita sorpresas.
        if ($codigoUnidad === '') {
            $errores[] = 'Celda B4: falta el codigo de la unidad administrativa.';
        } elseif ($periodo) {
            $unidad = $this->unidadDelPeriodo($periodo->id, $codigoUnidad);

            if (! $unidad) {
                $errores[] = 'No se encontro la unidad con codigo "'.$codigoUnidad.'" en el periodo '.$periodo->nombre.'.';
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
                if ($unidad && ClasificacionDocumentalTVD::where('unidad_id', $unidad->id)->where('cod', $colB)->exists()) {
                    $errores[] = 'Fila '.($index + 1).': la serie '.$colB.' ya existe en la unidad.';
                }

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
            'periodo' => $periodo?->nombre,
            'unidad' => $unidad ? $unidad->codigo.' - '.$unidad->nombre : null,
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
