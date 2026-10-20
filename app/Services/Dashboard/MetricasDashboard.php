<?php

namespace App\Services\Dashboard;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Utilidades comunes de los dashboards: consultas con el alcance ABAC del usuario, series por
 * día/mes resueltas con una sola consulta agrupada y variaciones frente al periodo anterior.
 * Ninguna cifra se inventa: si no hay datos, la serie va en ceros.
 */
abstract class MetricasDashboard
{
    /** Estados de trabajo con los que un radicado ya no requiere gestión. */
    protected const ESTADOS_CERRADOS = ['FINALIZADO', 'TRAMITADO', 'ANULADO'];

    /** Estados de PQRS ya atendidas. */
    protected const PQRS_ATENDIDAS = ['Respondida', 'Cerrado'];

    /** @var array<int, array{id: int, nombre: string, codigo: string}|null>|null */
    private ?array $dependenciaPorNodo = null;

    /**
     * Consultas base de radicación con el alcance jerárquico (ABAC) del usuario.
     *
     * @return array{recibidos: Builder, enviados: Builder, internos: Builder, pqrs: Builder}
     */
    protected function alcance(User $user): array
    {
        return [
            'recibidos' => VentanillaRadicaReci::conPermisoJerarquico($user),
            'enviados' => VentanillaRadicaEnviados::conPermisoJerarquico($user),
            'internos' => VentanillaRadicaInterno::conPermisoJerarquico($user),
            'pqrs' => VentanillaPqrs::conPermisoJerarquico($user)->whereNotNull('ventanilla_radica_reci_id'),
        ];
    }

    /**
     * Conteo por día de los últimos $dias días (incluido hoy), del más antiguo al más reciente.
     *
     * @return array<int, int>
     */
    protected function conteoPorDia(Builder $query, int $dias, string $columna = 'created_at'): array
    {
        $inicio = now()->subDays($dias - 1)->startOfDay();
        $porFecha = (clone $query)
            ->where($columna, '>=', $inicio)
            ->selectRaw("DATE({$columna}) as fecha, COUNT(*) as total")
            ->groupBy('fecha')
            ->pluck('total', 'fecha');

        $serie = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $serie[] = (int) ($porFecha[now()->subDays($i)->toDateString()] ?? 0);
        }

        return $serie;
    }

    /**
     * Conteo por mes de los últimos $meses meses (incluido el actual), del más antiguo al más reciente.
     *
     * @return array<int, int>
     */
    protected function conteoPorMes(Builder $query, int $meses, string $columna = 'created_at'): array
    {
        $inicio = now()->startOfMonth()->subMonths($meses - 1);
        $porMes = (clone $query)
            ->where($columna, '>=', $inicio)
            ->selectRaw("DATE_FORMAT({$columna}, '%Y-%m') as mes, COUNT(*) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $serie = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $serie[] = (int) ($porMes[now()->startOfMonth()->subMonths($i)->format('Y-m')] ?? 0);
        }

        return $serie;
    }

    /**
     * Conteos de dos periodos consecutivos de igual duración: [actual, anterior].
     *
     * @return array{0: int, 1: int}
     */
    protected function conteoPeriodos(Builder $query, int $dias, string $columna = 'created_at'): array
    {
        $inicioActual = now()->subDays($dias - 1)->startOfDay();
        $inicioAnterior = (clone $inicioActual)->subDays($dias);

        return [
            (clone $query)->where($columna, '>=', $inicioActual)->count(),
            (clone $query)->where($columna, '>=', $inicioAnterior)->where($columna, '<', $inicioActual)->count(),
        ];
    }

    /**
     * Conteos del mes en curso y del mes anterior: [actual, anterior].
     *
     * @return array{0: int, 1: int}
     */
    protected function conteoMeses(Builder $query, string $columna = 'created_at'): array
    {
        $inicioMes = now()->startOfMonth();

        return [
            (clone $query)->where($columna, '>=', $inicioMes)->count(),
            (clone $query)->where($columna, '>=', (clone $inicioMes)->subMonth())->where($columna, '<', $inicioMes)->count(),
        ];
    }

    /** Etiquetas cortas de los últimos $meses meses (p. ej. "Ago", "Sep", "Oct"). */
    protected function etiquetasMeses(int $meses): array
    {
        $etiquetas = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $etiquetas[] = $this->capital(now()->startOfMonth()->subMonths($i)->locale('es')->isoFormat('MMM'));
        }

        return $etiquetas;
    }

    /** Etiquetas cortas de los últimos $dias días (p. ej. "Lu", "Ma"). */
    protected function etiquetasDias(int $dias): array
    {
        $etiquetas = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $etiquetas[] = $this->capital(now()->subDays($i)->locale('es')->isoFormat('dd'));
        }

        return $etiquetas;
    }

    /** Variación porcentual del periodo actual frente al anterior (100 si antes no había nada). */
    protected function variacion(int|float $actual, int|float $anterior): float
    {
        if ($anterior == 0) {
            return $actual > 0 ? 100.0 : 0.0;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    /** Variación formateada con signo, p. ej. "+12.5%" o "-3%". */
    protected function formatoVariacion(float $variacion): string
    {
        return ($variacion >= 0 ? '+' : '').$this->numero($variacion).'%';
    }

    protected function tendencia(float $variacion): string
    {
        return $variacion < 0 ? 'negative' : 'positive';
    }

    /** Porcentaje de $parte sobre $total, redondeado a 1 decimal (0 si no hay total). */
    protected function porcentaje(int|float $parte, int|float $total): float
    {
        return $total > 0 ? round(($parte / $total) * 100, 1) : 0.0;
    }

    /** Número sin decimales sobrantes: 12.0 → "12", 12.5 → "12.5". */
    protected function numero(int|float $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 1, '.', ''), '0'), '.');
    }

    protected function nombreUsuario(?User $user): string
    {
        if (! $user) {
            return 'Sin asignar';
        }

        return trim("{$user->nombres} {$user->apellidos}") ?: $user->email;
    }

    /**
     * Dependencia (nodo tipo Dependencia más cercano hacia la raíz) de un nodo del organigrama.
     *
     * @return array{id: int, nombre: string, codigo: string}|null
     */
    protected function dependenciaDeNodo(?int $nodoId): ?array
    {
        if (! $nodoId) {
            return null;
        }

        if ($this->dependenciaPorNodo === null) {
            $nodos = CalidadOrganigrama::query()->get(['id', 'tipo', 'nom_organico', 'cod_organico', 'parent'])->keyBy('id');
            $this->dependenciaPorNodo = [];

            foreach ($nodos as $id => $nodo) {
                $actual = $nodo;
                $visitados = [];
                while ($actual && $actual->tipo !== 'Dependencia' && ! isset($visitados[$actual->id])) {
                    $visitados[$actual->id] = true;
                    $actual = $actual->parent ? $nodos->get($actual->parent) : null;
                }

                $this->dependenciaPorNodo[$id] = $actual
                    ? ['id' => $actual->id, 'nombre' => $actual->nom_organico, 'codigo' => $actual->cod_organico]
                    : null;
            }
        }

        return $this->dependenciaPorNodo[$nodoId] ?? null;
    }

    /**
     * Agrupa conteos por cargo ([cargo_id => total]) en conteos por dependencia, de mayor a menor.
     *
     * @param  iterable<int, int>  $conteoPorCargo
     * @return array<int, array{id: int, nombre: string, codigo: string, total: int}>
     */
    protected function sumarPorDependencia(iterable $conteoPorCargo): array
    {
        $resultado = [];
        foreach ($conteoPorCargo as $cargoId => $total) {
            $dependencia = $this->dependenciaDeNodo((int) $cargoId);
            if (! $dependencia) {
                continue;
            }
            $resultado[$dependencia['id']] ??= $dependencia + ['total' => 0];
            $resultado[$dependencia['id']]['total'] += (int) $total;
        }

        usort($resultado, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $resultado;
    }

    /** Fecha corta en español, p. ej. "17 oct 2026". */
    protected function fechaCorta($fecha): ?string
    {
        return $fecha ? Carbon::parse($fecha)->locale('es')->isoFormat('D MMM YYYY') : null;
    }

    private function capital(string $texto): string
    {
        return mb_strtoupper(mb_substr($texto, 0, 1)).rtrim(mb_substr($texto, 1), '.');
    }
}
