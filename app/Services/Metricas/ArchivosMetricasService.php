<?php

namespace App\Services\Metricas;

use App\Models\Metricas\ArchivoMetrica;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Agregados de archivos_metricas para el tablero: totales, serie por día o mes y distribución
 * por módulo, formato y dependencia. Todo se resuelve con GROUP BY sobre los índices de la tabla.
 */
class ArchivosMetricasService
{
    public const MODULOS = ['ventanilla_unica', 'mi_bandeja', 'gestion_archivo', 'workflows'];

    private const SUMAS = "SUM(evento = 'cargado') AS archivos, "
        ."SUM(CASE WHEN evento = 'cargado' THEN bytes ELSE 0 END) AS bytes, "
        ."SUM(evento = 'eliminado') AS archivos_eliminados, "
        ."SUM(CASE WHEN evento = 'eliminado' THEN bytes ELSE 0 END) AS bytes_eliminados";

    private const LIMITE_FORMATOS = 12;

    private const LIMITE_DEPENDENCIAS = 10;

    /**
     * @param  array{desde: string, hasta: string, agrupacion: string, modulo?: ?string, dependencia_id?: ?int}  $filtros
     */
    public function resumen(array $filtros): array
    {
        $desde = Carbon::parse($filtros['desde'])->startOfDay();
        $hasta = Carbon::parse($filtros['hasta'])->endOfDay();
        $porMes = $filtros['agrupacion'] === 'mes';

        $enRango = fn () => $this->base($filtros)->whereBetween('ocurrido_en', [$desde, $hasta]);
        $cargados = fn () => $enRango()->where('evento', ArchivoMetrica::CARGADO);

        $totales = $this->sumas($enRango()->selectRaw(self::SUMAS)->toBase()->first());
        $totales['bytes_netos'] = $totales['bytes'] - $totales['bytes_eliminados'];
        $totales['peso_promedio'] = $totales['archivos'] ? (int) round($totales['bytes'] / $totales['archivos']) : 0;

        // Lo almacenado a la fecha (sin el rango): todo lo cargado menos lo eliminado
        $historico = $this->sumas($this->base($filtros)->where('ocurrido_en', '<=', $hasta)->selectRaw(self::SUMAS)->toBase()->first());

        $periodo = $porMes ? "DATE_FORMAT(ocurrido_en, '%Y-%m')" : 'DATE(ocurrido_en)';
        $serie = $enRango()
            ->selectRaw("{$periodo} AS periodo, ".self::SUMAS)
            ->groupByRaw($periodo)
            ->toBase()
            ->get()
            ->keyBy('periodo');

        return [
            'filtros' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'agrupacion' => $porMes ? 'mes' : 'dia',
                'modulo' => $filtros['modulo'] ?? null,
                'dependencia_id' => $filtros['dependencia_id'] ?? null,
            ],
            'totales' => $totales,
            'almacenamiento' => [
                'archivos' => $historico['archivos'] - $historico['archivos_eliminados'],
                'bytes' => $historico['bytes'] - $historico['bytes_eliminados'],
            ],
            'serie' => $this->serieCompleta($serie, $desde, $hasta, $porMes),
            'por_modulo' => $cargados()
                ->selectRaw('modulo, submodulo, COUNT(*) AS archivos, SUM(bytes) AS bytes')
                ->groupBy('modulo', 'submodulo')
                ->orderByDesc('archivos')
                ->toBase()->get()
                ->map(fn ($f) => ['modulo' => $f->modulo, 'submodulo' => $f->submodulo] + $this->enteros($f, ['archivos', 'bytes']))
                ->all(),
            'por_formato' => $cargados()
                ->selectRaw('extension, COUNT(*) AS archivos, SUM(bytes) AS bytes')
                ->groupBy('extension')
                ->orderByDesc('archivos')
                ->limit(self::LIMITE_FORMATOS)
                ->toBase()->get()
                ->map(fn ($f) => ['extension' => $f->extension] + $this->enteros($f, ['archivos', 'bytes']))
                ->all(),
            'por_dependencia' => $cargados()
                ->whereNotNull('dependencia_id')
                ->selectRaw('dependencia_id, MAX(dependencia_nom) AS dependencia, COUNT(*) AS archivos, SUM(bytes) AS bytes')
                ->groupBy('dependencia_id')
                ->orderByDesc('bytes')
                ->limit(self::LIMITE_DEPENDENCIAS)
                ->toBase()->get()
                ->map(fn ($f) => ['dependencia_id' => (int) $f->dependencia_id, 'dependencia' => $f->dependencia]
                    + $this->enteros($f, ['archivos', 'bytes']))
                ->all(),
        ];
    }

    private function base(array $filtros): Builder
    {
        return ArchivoMetrica::query()
            ->when($filtros['modulo'] ?? null, fn ($q, $modulo) => $q->where('modulo', $modulo))
            ->when($filtros['dependencia_id'] ?? null, fn ($q, $id) => $q->where('dependencia_id', $id));
    }

    /**
     * Un punto por día o mes del rango, con ceros donde no hubo movimiento (gráficas continuas).
     */
    private function serieCompleta($filas, Carbon $desde, Carbon $hasta, bool $porMes): array
    {
        $periodos = $porMes
            ? CarbonPeriod::create($desde->copy()->startOfMonth(), '1 month', $hasta->copy()->startOfMonth())
            : CarbonPeriod::create($desde->copy()->startOfDay(), '1 day', $hasta->copy()->startOfDay());

        $serie = [];
        foreach ($periodos as $fecha) {
            $clave = $porMes ? $fecha->format('Y-m') : $fecha->toDateString();
            $serie[] = ['periodo' => $clave] + $this->sumas($filas->get($clave));
        }

        return $serie;
    }

    /**
     * @return array{archivos: int, bytes: int, archivos_eliminados: int, bytes_eliminados: int}
     */
    private function sumas(?object $fila): array
    {
        return $this->enteros($fila, ['archivos', 'bytes', 'archivos_eliminados', 'bytes_eliminados']);
    }

    private function enteros(?object $fila, array $campos): array
    {
        return collect($campos)->mapWithKeys(fn ($c) => [$c => (int) ($fila->{$c} ?? 0)])->all();
    }
}
