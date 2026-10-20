<?php

namespace App\Services\Dashboard;

use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard Logistics de la plantilla aplicado a la distribución de la correspondencia enviada:
 * flujo mensual, medios de envío, firma, anulaciones y respuestas a radicados recibidos,
 * siempre dentro del alcance ABAC del usuario.
 */
class LogisticsDashboardService extends MetricasDashboard
{
    /** Estado de envío derivado del radicado; el mismo CASE alimenta el donut y la tabla. */
    private const ESTADO_ENVIO = "CASE
        WHEN estado_trabajo = 'ANULADO' THEN 'Anulado'
        WHEN usua_soli_anula_id IS NOT NULL THEN 'Anulación solicitada'
        WHEN estado_firma = 'firmado' THEN 'Firmado'
        WHEN estado_trabajo = 'VENCIDO' THEN 'Vencido'
        ELSE 'Pendiente de firma'
    END";

    /** Avance del envío según su estado (radicado → firmado). */
    private const AVANCE = [
        'Pendiente de firma' => 50,
        'Vencido' => 50,
        'Anulación solicitada' => 50,
        'Firmado' => 100,
        'Anulado' => 0,
    ];

    /** Colores de la barra de medios de envío, en el orden de la plantilla. */
    private const COLORES_MEDIOS = [
        ['action', 'hover'],
        ['primary', 'main'],
        ['info', 'main'],
        ['SnackbarContent', 'bg'],
    ];

    public function datos(User $user): array
    {
        $alcance = $this->alcance($user);
        $enviados = $alcance['enviados'];
        $recibidos = $alcance['recibidos'];
        $tabla = $this->ultimosEnviados($enviados);

        return [
            'stats_horizontal' => $this->tarjetas($enviados),
            'vehicle_overview' => $this->porMedio($enviados),
            'shipment_stats' => $this->flujoMensual($enviados, $recibidos),
            'delivery_performance' => $this->indicadores($enviados, $recibidos),
            'delivery_exceptions' => $this->porEstado($enviados),
            'orders_by_sedes' => $this->trazabilidad($tabla),
            'vehicles_table' => $tabla->map(fn ($fila) => [
                'id' => $fila['id'],
                'location' => $fila['radicado'],
                'startCity' => $fila['origen'],
                'endCity' => $fila['destinatario'],
                'warnings' => $fila['estado'],
                'progress' => self::AVANCE[$fila['estado']] ?? 0,
            ])->values()->all(),
        ];
    }

    /** Flujo del mes en curso frente al anterior. */
    private function tarjetas(Builder $enviados): array
    {
        $conRespuesta = (clone $enviados)->whereHas('recibidos');
        $destinatarios = fn ($desde, $hasta) => (clone $enviados)
            ->where('created_at', '>=', $desde)->where('created_at', '<', $hasta)
            ->distinct()->count('tercero_id');
        $inicioMes = now()->startOfMonth();

        $tarjetas = [
            ['Radicados enviados', $this->conteoMeses($enviados), 'tabler-send', 'primary'],
            ['Firmados', $this->conteoMeses((clone $enviados)->where('estado_firma', 'firmado'), 'fecha_firma'), 'tabler-signature', 'success'],
            ['Respuestas a recibidos', $this->conteoMeses($conRespuesta), 'tabler-arrow-back-up', 'info'],
            ['Destinatarios', [
                $destinatarios($inicioMes, now()->addSecond()),
                $destinatarios((clone $inicioMes)->subMonth(), $inicioMes),
            ], 'tabler-users', 'warning'],
        ];

        return array_map(fn ($t) => [
            'title' => $t[0],
            'stats' => (string) $t[1][0],
            'trendNumber' => $this->variacion($t[1][0], $t[1][1]),
            'trendLabel' => 'este mes vs. el anterior',
            'avatarIcon' => $t[2],
            'color' => $t[3],
        ], $tarjetas);
    }

    /** Distribución de los envíos por medio (lista TipRecep): las 3 mayores y el resto agrupado. */
    private function porMedio(Builder $enviados): array
    {
        $conteo = (clone $enviados)
            ->selectRaw('medio_enviado_id, COUNT(*) as total')
            ->groupBy('medio_enviado_id')
            ->orderByDesc('total')
            ->pluck('total', 'medio_enviado_id');
        $total = $conteo->sum();
        if (! $total) {
            return [];
        }

        $nombres = ConfigListaDetalle::whereIn('id', $conteo->keys()->filter())->pluck('nombre', 'id');
        $grupos = $conteo->map(fn ($n, $id) => ['nombre' => $nombres[$id] ?? 'Sin medio', 'total' => (int) $n])->values();
        if ($grupos->count() > 4) {
            $grupos = $grupos->take(3)->push(['nombre' => 'Otros medios', 'total' => $grupos->slice(3)->sum('total')]);
        }

        return $grupos->values()->map(fn ($g, $i) => [
            'icon' => $this->iconoMedio($g['nombre']),
            'heading' => $g['nombre'],
            'time' => $g['total'].' '.($g['total'] === 1 ? 'envío' : 'envíos'),
            'progressColor' => self::COLORES_MEDIOS[$i][0],
            'progressColorVariant' => self::COLORES_MEDIOS[$i][1],
            'progressData' => $this->numero($this->porcentaje($g['total'], $total)).'%',
        ])->all();
    }

    /** Enviados frente a recibidos en los últimos 6 meses. */
    private function flujoMensual(Builder $enviados, Builder $recibidos): array
    {
        $serieEnviados = $this->conteoPorMes($enviados, 6);

        return [
            'series' => [
                ['name' => 'Enviados', 'type' => 'column', 'data' => $serieEnviados],
                ['name' => 'Recibidos', 'type' => 'line', 'data' => $this->conteoPorMes($recibidos, 6)],
            ],
            'categories' => $this->etiquetasMeses(6),
            'total' => array_sum($serieEnviados),
        ];
    }

    /** Indicadores de gestión; 'change' solo cuando hay un periodo comparable. */
    private function indicadores(Builder $enviados, Builder $recibidos): array
    {
        $pendientes = (clone $enviados)->where('estado_firma', '!=', 'firmado')
            ->where(fn ($q) => $q->whereNull('estado_trabajo')->orWhere('estado_trabajo', '!=', 'ANULADO'))->count();
        $anulados = (clone $enviados)->where('estado_trabajo', 'ANULADO')->count();

        $recibidosVigentes = (clone $recibidos)
            ->where(fn ($q) => $q->whereNull('estado_trabajo')->orWhere('estado_trabajo', '!=', 'ANULADO'));
        $respondidos = (clone $recibidosVigentes)
            ->whereIn('id', DB::table('ventanilla_radica_enviados_respuestas')->select('radica_reci_id'))->count();

        $diasRespuesta = DB::table('ventanilla_radica_enviados_respuestas as r')
            ->join('ventanilla_radica_enviados as e', 'e.id', '=', 'r.radica_enviado_id')
            ->join('ventanilla_radica_reci as c', 'c.id', '=', 'r.radica_reci_id')
            ->whereIn('r.radica_enviado_id', (clone $enviados)->select('ventanilla_radica_enviados.id'))
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, c.created_at, e.created_at)) / 24 as dias')
            ->value('dias');
        $diasFirma = (clone $enviados)->where('estado_firma', 'firmado')->whereNotNull('fecha_firma')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, fecha_firma)) / 24 as dias')
            ->value('dias');

        $inicioMes = now()->startOfMonth();
        $folios = fn ($desde, $hasta) => (int) (clone $enviados)
            ->where('created_at', '>=', $desde)->where('created_at', '<', $hasta)->sum('num_folios');
        $foliosMes = $folios($inicioMes, now()->addSecond());

        return [
            ['title' => 'Pendientes de firma', 'value' => (string) $pendientes, 'change' => null, 'icon' => 'tabler-hourglass', 'color' => 'warning'],
            ['title' => 'Anulados', 'value' => (string) $anulados, 'change' => null, 'icon' => 'tabler-ban', 'color' => 'error'],
            [
                'title' => 'Recibidos con respuesta',
                'value' => $this->numero($this->porcentaje($respondidos, (clone $recibidosVigentes)->count())).'%',
                'change' => null, 'icon' => 'tabler-percentage', 'color' => 'primary',
            ],
            ['title' => 'Tiempo promedio de respuesta', 'value' => $this->dias($diasRespuesta), 'change' => null, 'icon' => 'tabler-clock', 'color' => 'secondary'],
            ['title' => 'Tiempo promedio hasta la firma', 'value' => $this->dias($diasFirma), 'change' => null, 'icon' => 'tabler-signature', 'color' => 'info'],
            [
                'title' => 'Folios despachados en el mes',
                'value' => (string) $foliosMes,
                'change' => $this->variacion($foliosMes, $folios((clone $inicioMes)->subMonth(), $inicioMes)),
                'icon' => 'tabler-files', 'color' => 'success',
            ],
        ];
    }

    /** Envíos agrupados por su estado (donut). */
    private function porEstado(Builder $enviados): array
    {
        $conteo = (clone $enviados)
            ->selectRaw(self::ESTADO_ENVIO.' as estado_envio, COUNT(*) as total')
            ->groupBy('estado_envio')
            ->orderByDesc('total')
            ->pluck('total', 'estado_envio');

        return [
            'labels' => $conteo->keys()->all(),
            'series' => $conteo->values()->map(fn ($n) => (int) $n)->all(),
            'total' => (int) $conteo->sum(),
        ];
    }

    /** Últimos radicados por estado: dependencia de origen → destinatario. */
    private function trazabilidad(Collection $tabla): array
    {
        $tab = fn (array $estados) => $tabla->whereIn('estado', $estados)->take(2)->map(fn ($f) => [
            'id' => $f['id'],
            'radicado' => $f['radicado'],
            'sender' => ['name' => $f['origen'], 'address' => "{$f['radicado']} · {$f['creador']}"],
            'receiver' => ['name' => $f['destinatario'], 'address' => $f['destino']],
        ])->values()->all();

        return [
            'pendientes' => $tab(['Pendiente de firma', 'Vencido', 'Anulación solicitada']),
            'firmados' => $tab(['Firmado']),
            'anulados' => $tab(['Anulado']),
        ];
    }

    /** Últimos 20 enviados del alcance con origen, destino y estado. */
    private function ultimosEnviados(Builder $enviados): Collection
    {
        $filas = (clone $enviados)
            ->select('ventanilla_radica_enviados.*')
            ->selectRaw(self::ESTADO_ENVIO.' as estado_envio')
            ->with(['tercero.divisionPolitica.padre', 'medioEnvio', 'usuarioCreaRadicado'])
            ->latest('created_at')->latest('id')
            ->limit(20)
            ->get();

        $cargoPorUsuario = UserCargo::whereIn('user_id', $filas->pluck('usuario_crea')->filter()->unique())
            ->where('estado', true)->whereNull('fecha_fin')
            ->pluck('cargo_id', 'user_id');

        return $filas->map(function ($e) use ($cargoPorUsuario) {
            $creador = $this->nombreUsuario($e->usuarioCreaRadicado);
            $municipio = $e->tercero?->divisionPolitica;
            $ubicacion = $municipio
                ? implode(', ', array_filter([$municipio->nombre, $municipio->padre?->nombre]))
                : 'Sin ubicación registrada';

            return [
                'id' => $e->id,
                'radicado' => $e->num_radicado,
                'creador' => $creador,
                'origen' => $this->dependenciaDeNodo($cargoPorUsuario[$e->usuario_crea] ?? null)['nombre'] ?? $creador,
                'destinatario' => $e->tercero?->nom_razo_soci ?: 'Sin destinatario',
                'destino' => $ubicacion.($e->medioEnvio ? " · {$e->medioEnvio->nombre}" : ''),
                'estado' => $e->estado_envio,
            ];
        });
    }

    private function dias($promedio): string
    {
        if ($promedio === null) {
            return '—';
        }
        $valor = $this->numero(round((float) $promedio, 1));

        return $valor.($valor === '1' ? ' día' : ' días');
    }

    private function iconoMedio(string $nombre): string
    {
        $nombre = mb_strtolower($nombre);

        return match (true) {
            str_contains($nombre, 'electr'), str_contains($nombre, 'web') => 'tabler-mail-fast',
            str_contains($nombre, 'certificado') => 'tabler-mail-check',
            str_contains($nombre, 'mensajer') => 'tabler-truck',
            str_contains($nombre, 'personal'), str_contains($nombre, 'verbal') => 'tabler-building-bank',
            default => 'tabler-mail',
        };
    }
}
