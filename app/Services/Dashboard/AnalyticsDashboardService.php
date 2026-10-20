<?php

namespace App\Services\Dashboard;

use App\Helpers\CalendarioHelper;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos del dashboard Analytics de la plantilla: radicación, control de términos y PQRS dentro
 * del alcance ABAC del usuario. Las tendencias comparan con el periodo anterior equivalente.
 */
class AnalyticsDashboardService extends MetricasDashboard
{
    private const COLORES = ['primary', 'success', 'info', 'warning', 'secondary', 'error'];

    /** Máximo de filas de la tabla de vencimientos. */
    private const MAX_VENCIMIENTOS = 50;

    /** @var array{recibidos: Builder, enviados: Builder, internos: Builder, pqrs: Builder} */
    private array $alcance;

    public function datos(User $user): array
    {
        $this->alcance = $this->alcance($user);

        return [
            'slider' => $this->slider(),
            'daily_chart' => $this->radicacionDiaria(),
            'overview' => $this->resumenRecibidos(),
            'earning_weekly' => $this->balanceSemanal(),
            'support_tracker' => $this->seguimientoPqrs(),
            'dependencias' => $this->cargaPorDependencia(),
            'total_earning' => $this->flujoSemestral(),
            'canales' => $this->canalesRecepcion(),
            'tipos_tramite' => $this->tiposPqrs(),
            'project_table' => $this->vencimientos(),
        ];
    }

    private function q(string $tipo): Builder
    {
        return clone $this->alcance[$tipo];
    }

    private function slider(): array
    {
        $hoy = CalendarioHelper::hoy()->toDateString();
        $limite = CalendarioHelper::hoy()->addDays(7)->toDateString();
        $abiertos = fn () => $this->q('recibidos')->whereNotIn('estado_trabajo', self::ESTADOS_CERRADOS);

        $documentos = 0;
        $conHash = 0;
        foreach (['recibidos', 'enviados', 'internos'] as $tipo) {
            $documentos += $this->q($tipo)->count();
            $conHash += $this->q($tipo)->whereNotNull('hash_sha256')->where('hash_sha256', '!=', '')->count();
        }

        $usuariosActivos = User::where('estado', 1)->count();
        $usuarios2fa = User::where('estado', 1)->whereNotNull('two_factor_confirmed_at')->count();

        return [
            [
                'title' => 'Gestión Documental',
                'img' => '/images/cards/graphic-illustration-1.png',
                'details' => [
                    'Recibidos' => $this->compacto($this->q('recibidos')->count()),
                    'Enviados' => $this->compacto($this->q('enviados')->count()),
                    'Internos' => $this->compacto($this->q('internos')->count()),
                    'PQRS' => $this->compacto($this->q('pqrs')->count()),
                ],
            ],
            [
                'title' => 'Control de Términos',
                'img' => '/images/cards/graphic-illustration-2.png',
                'details' => [
                    'En Término' => $this->compacto($abiertos()->where(fn ($q) => $q->whereNull('fec_venci')->orWhere('fec_venci', '>', $limite))->count()),
                    'Por Vencer (7d)' => $this->compacto($abiertos()->whereBetween('fec_venci', [$hoy, $limite])->count()),
                    'Vencidos' => $this->compacto($abiertos()->where('fec_venci', '<', $hoy)->count()),
                    'Tramitados' => $this->compacto($this->q('recibidos')->whereIn('estado_trabajo', ['TRAMITADO', 'FINALIZADO'])->count()),
                ],
            ],
            [
                // Agregados institucionales: no exponen asuntos ni remitentes
                'title' => 'Seguridad y Firmas',
                'img' => '/images/cards/graphic-illustration-3.png',
                'details' => [
                    'Firmas Hoy' => $this->compacto(FirmaEvento::where('created_at', '>=', now()->startOfDay())->count()),
                    'Firmas Mes' => $this->compacto(FirmaEvento::where('created_at', '>=', now()->startOfMonth())->count()),
                    'Docs con Hash' => round($this->porcentaje($conHash, $documentos)).'%',
                    'Usuarios 2FA' => "{$usuarios2fa}/{$usuariosActivos}",
                ],
            ],
        ];
    }

    private function radicacionDiaria(): array
    {
        $serie = $this->conteoPorDia($this->q('recibidos'), 7);

        return [
            'series' => [['name' => 'Radicados', 'data' => $serie]],
            'categories' => $this->etiquetasDias(7),
            'total_hoy' => end($serie),
        ];
    }

    /** Radicados recibidos: tramitados frente a pendientes y variación del mes. */
    private function resumenRecibidos(): array
    {
        $vigentes = fn () => $this->q('recibidos')->where(fn ($q) => $q->whereNull('estado_trabajo')->orWhere('estado_trabajo', '!=', 'ANULADO'));
        $total = $vigentes()->count();
        $tramitados = $vigentes()->whereIn('estado_trabajo', ['TRAMITADO', 'FINALIZADO'])->count();
        $pendientes = $total - $tramitados;
        [$actual, $anterior] = $this->conteoMeses($this->q('recibidos'));
        $variacion = $this->variacion($actual, $anterior);

        return [
            'titulo' => 'Radicados Recibidos',
            'total_documentos' => $total,
            'trend' => $this->formatoVariacion($variacion),
            'tendencia' => $this->tendencia($variacion),
            'tramitados' => $tramitados,
            'pendientes' => $pendientes,
            'porcentaje_tramitados' => $this->porcentaje($tramitados, $total),
            'porcentaje_pendientes' => $this->porcentaje($pendientes, $total),
        ];
    }

    /** Radicación de los últimos 7 días frente a los 7 anteriores, por tipo de comunicación. */
    private function balanceSemanal(): array
    {
        $tipos = [
            'recibidos' => ['Recibidos', 'primary', 'tabler-mail'],
            'enviados' => ['Enviados', 'info', 'tabler-send'],
            'internos' => ['Internos', 'warning', 'tabler-arrows-left-right'],
        ];

        $porDia = array_fill(0, 7, 0);
        $actual = 0;
        $anterior = 0;
        $conteos = [];
        foreach (array_keys($tipos) as $tipo) {
            foreach ($this->conteoPorDia($this->q($tipo), 7) as $i => $n) {
                $porDia[$i] += $n;
            }
            [$conteos[$tipo], $previo] = $this->conteoPeriodos($this->q($tipo), 7);
            $actual += $conteos[$tipo];
            $anterior += $previo;
        }
        $variacion = $this->variacion($actual, $anterior);

        $cards = [];
        foreach ($tipos as $tipo => [$titulo, $color, $icono]) {
            $cards[] = [
                'title' => $titulo,
                'stats' => $this->compacto($conteos[$tipo]),
                'progress' => $this->porcentaje($conteos[$tipo], $actual),
                'progressColor' => $color,
                'avatarColor' => $color,
                'avatarIcon' => $icono,
            ];
        }

        return [
            'total' => $actual,
            'trend' => $this->formatoVariacion($variacion),
            'tendencia' => $this->tendencia($variacion),
            'series' => [['name' => 'Radicados', 'data' => $porDia]],
            'categories' => $this->etiquetasDias(7),
            'cards' => $cards,
        ];
    }

    private function seguimientoPqrs(): array
    {
        $total = $this->q('pqrs')->count();
        $atendidas = $this->q('pqrs')->whereIn('estado_tramite', self::PQRS_ATENDIDAS)->count();
        $promedio = $this->q('pqrs')
            ->whereNotNull('fecha_respuesta')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, ventanilla_pqrs.created_at, fecha_respuesta)) as horas')
            ->value('horas');

        return [
            'total_tickets' => $total,
            'nuevas' => $this->conteoPeriodos($this->q('pqrs'), 7)[0],
            'tickets_abiertos' => $total - $atendidas,
            'tiempo_respuesta' => $promedio === null ? '—' : $this->numero(max(0, $promedio) / 24).' días',
            'porcentaje_completado' => $this->porcentaje($atendidas, $total),
        ];
    }

    /** Radicados recibidos por dependencia del custodio, con variación del mes frente al anterior. */
    private function cargaPorDependencia(): array
    {
        $inicioMes = now()->startOfMonth();
        $filas = DB::table('ventanilla_radica_reci_responsa as r')
            ->join('users_cargos as uc', 'uc.id', '=', 'r.users_cargos_id')
            ->join('ventanilla_radica_reci as v', 'v.id', '=', 'r.radica_reci_id')
            ->where('r.custodio', 1)
            ->whereIn('r.radica_reci_id', $this->q('recibidos')->select('ventanilla_radica_reci.id'))
            ->groupBy('uc.cargo_id')
            ->selectRaw('uc.cargo_id, COUNT(DISTINCT v.id) as total')
            ->selectRaw('COUNT(DISTINCT CASE WHEN v.created_at >= ? THEN v.id END) as actual', [$inicioMes])
            ->selectRaw('COUNT(DISTINCT CASE WHEN v.created_at >= ? AND v.created_at < ? THEN v.id END) as anterior', [(clone $inicioMes)->subMonth(), $inicioMes])
            ->get();

        $porMes = fn (string $campo) => collect($this->sumarPorDependencia($filas->pluck($campo, 'cargo_id')))->pluck('total', 'id');
        $actual = $porMes('actual');
        $anterior = $porMes('anterior');

        return collect($this->sumarPorDependencia($filas->pluck('total', 'cargo_id')))
            ->take(6)
            ->values()
            ->map(function ($dep, $i) use ($actual, $anterior) {
                $variacion = $this->variacion($actual[$dep['id']] ?? 0, $anterior[$dep['id']] ?? 0);

                return [
                    'title' => $this->compacto($dep['total']),
                    'subtitle' => $dep['nombre'],
                    'trendNumber' => $this->numero(abs($variacion)),
                    'trend' => $this->tendencia($variacion),
                    'avatarColor' => self::COLORES[$i % count(self::COLORES)],
                    'icon' => 'tabler-building',
                ];
            })
            ->all();
    }

    /** Recibidos (positivo) frente a enviados (negativo) de los últimos 6 meses. */
    private function flujoSemestral(): array
    {
        $recibidos = $this->conteoPorMes($this->q('recibidos'), 6);
        $enviados = $this->conteoPorMes($this->q('enviados'), 6);
        $mensual = fn (array $serie) => $this->variacion($serie[5], $serie[4]);
        $variacion = $this->variacion($recibidos[5] + $enviados[5], $recibidos[4] + $enviados[4]);

        return [
            'series' => [
                ['name' => 'Recibidos', 'data' => $recibidos],
                ['name' => 'Enviados', 'data' => array_map(fn ($n) => -$n, $enviados)],
            ],
            'categories' => $this->etiquetasMeses(6),
            'total' => array_sum($recibidos) + array_sum($enviados),
            'trend' => $this->formatoVariacion($variacion),
            'tendencia' => $this->tendencia($variacion),
            'total_recibidos' => array_sum($recibidos),
            'total_enviados' => array_sum($enviados),
            'tendencia_recibidos' => $this->tendencia($mensual($recibidos)),
            'tendencia_enviados' => $this->tendencia($mensual($enviados)),
        ];
    }

    /** Medios de recepción (lista TipRecep) con su volumen y la variación del mes. */
    private function canalesRecepcion(): array
    {
        return $this->porDetalleDeLista('TipRecep', $this->q('recibidos'), 'medio_recep_id')
            ->map(fn ($item, $i) => [
                'title' => $item['nombre'],
                'amount' => $this->compacto($item['total']),
                'trendNumber' => $this->numero(abs($item['variacion'])).'%',
                'trend' => $this->tendencia($item['variacion']),
                'avatarColor' => self::COLORES[$i % count(self::COLORES)],
                'icon' => $this->iconoCanal($item['nombre']),
            ])
            ->all();
    }

    /** Tipos de PQRSF (lista TipoPQRSF) con su término legal, volumen y variación del mes. */
    private function tiposPqrs(): array
    {
        return $this->porDetalleDeLista('TipoPQRSF', $this->q('pqrs'), 'tipo_pqrs_id')
            ->map(fn ($item) => [
                'title' => $item['nombre'],
                'subtitle' => 'Término legal '.VentanillaPqrs::diasTerminoPara($item['detalle']).' días',
                'amount' => $this->compacto($item['total']),
                'trendNumber' => $this->numero(abs($item['variacion'])),
                'trend' => $this->tendencia($item['variacion']),
                'icon' => $this->iconoTipoPqrs($item['nombre']),
            ])
            ->all();
    }

    /**
     * Conteo por cada valor activo de una lista de configuración (top 6, de mayor a menor).
     *
     * @return Collection<int, array{detalle: ConfigListaDetalle, nombre: string, total: int, variacion: float}>
     */
    private function porDetalleDeLista(string $codigoLista, Builder $query, string $columna): Collection
    {
        $inicioMes = now()->startOfMonth();
        $tabla = $query->getModel()->getTable();
        $conteos = (clone $query)
            ->whereNotNull($columna)
            ->groupBy($columna)
            ->selectRaw("{$columna} as detalle_id, COUNT(*) as total")
            ->selectRaw("SUM({$tabla}.created_at >= ?) as actual", [$inicioMes])
            ->selectRaw("SUM({$tabla}.created_at >= ? AND {$tabla}.created_at < ?) as anterior", [(clone $inicioMes)->subMonth(), $inicioMes])
            ->toBase()
            ->get()
            ->keyBy('detalle_id');

        return ConfigListaDetalle::query()
            ->whereHas('lista', fn ($q) => $q->where('cod', $codigoLista))
            ->where(fn ($q) => $q->where('estado', 1)->orWhereIn('id', $conteos->keys()))
            ->get()
            ->map(fn ($detalle) => [
                'detalle' => $detalle,
                'nombre' => $detalle->nombre,
                'total' => (int) ($conteos[$detalle->id]->total ?? 0),
                'variacion' => $this->variacion((int) ($conteos[$detalle->id]->actual ?? 0), (int) ($conteos[$detalle->id]->anterior ?? 0)),
            ])
            ->sortByDesc('total')
            ->take(6)
            ->values();
    }

    /** Radicados recibidos y PQRS abiertos vencidos o que vencen en los próximos 7 días. */
    private function vencimientos(): array
    {
        $hoy = CalendarioHelper::hoy();
        $limite = (clone $hoy)->addDays(7)->toDateString();
        $filas = [];

        $recibidos = $this->q('recibidos')
            ->with('usuariosResponsables.user:id,nombres,apellidos,email,avatar')
            ->whereNotNull('fec_venci')
            ->where('fec_venci', '<=', $limite)
            ->whereNotIn('estado_trabajo', self::ESTADOS_CERRADOS)
            ->orderBy('fec_venci')
            ->limit(self::MAX_VENCIMIENTOS)
            ->get(['id', 'num_radicado', 'asunto', 'fec_venci', 'created_at']);

        foreach ($recibidos as $r) {
            $filas[] = $this->filaVencimiento("reci-{$r->id}", $r->num_radicado, $r->asunto, 'Radicado recibido', 'tabler-mail', $r->created_at, $r->fec_venci, $r->usuariosResponsables, $hoy);
        }

        $pqrs = $this->q('pqrs')
            ->with('radicado.usuariosResponsables.user:id,nombres,apellidos,email,avatar')
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<=', $limite)
            ->whereNotIn('estado_tramite', self::PQRS_ATENDIDAS)
            ->orderBy('fecha_vencimiento')
            ->limit(self::MAX_VENCIMIENTOS)
            ->get(['id', 'ventanilla_radica_reci_id', 'detalle_solicitud', 'fecha_vencimiento', 'created_at']);

        foreach ($pqrs as $p) {
            $filas[] = $this->filaVencimiento("pqrs-{$p->id}", $p->radicado?->num_radicado ?? "PQRS-{$p->id}", $p->detalle_solicitud, 'PQRS', 'tabler-message', $p->created_at, $p->fecha_vencimiento, $p->radicado?->usuariosResponsables ?? collect(), $hoy);
        }

        usort($filas, fn ($a, $b) => $a['fecha_vencimiento'] <=> $b['fecha_vencimiento']);

        return array_slice($filas, 0, self::MAX_VENCIMIENTOS);
    }

    /** Fila de la tabla: el avance es la fracción del término ya consumida (100 si venció). */
    private function filaVencimiento(string $id, ?string $referencia, ?string $asunto, string $tipo, string $icono, $creado, $vence, Collection $responsables, CarbonInterface $hoy): array
    {
        $inicio = Carbon::parse($creado)->startOfDay();
        $fin = Carbon::parse($vence)->startOfDay();
        $plazo = max(1, $inicio->diffInDays($fin));
        $dias = (int) $hoy->diffInDays($fin, false);

        return [
            'id' => $id,
            'title' => $referencia,
            'subtitle' => Str::limit((string) $asunto, 60),
            'icon' => $icono,
            'leader' => $tipo,
            'avatarGroup' => $responsables
                ->map(fn ($cargo) => $cargo->user)
                ->filter()
                ->unique('id')
                ->map(fn (User $u) => ['src' => $u->avatar_url, 'nombre' => $this->nombreUsuario($u)])
                ->values()
                ->all(),
            'status' => (int) min(100, max(0, round($inicio->diffInDays($hoy, false) / $plazo * 100))),
            'fecha_vencimiento' => $fin->toDateString(),
            'dias_restantes' => $dias,
        ];
    }

    /** Número abreviado para espacios reducidos: 1250 → "1.3k". */
    private function compacto(int $valor): string
    {
        return $valor >= 1000 ? $this->numero($valor / 1000).'k' : (string) $valor;
    }

    private function iconoCanal(string $nombre): string
    {
        $nombre = Str::lower(Str::ascii($nombre));

        return match (true) {
            str_contains($nombre, 'certificado') || str_contains($nombre, 'tradicional') => 'tabler-truck-delivery',
            str_contains($nombre, 'correo') || str_contains($nombre, 'mail') => 'tabler-mail',
            str_contains($nombre, 'buzon') => 'tabler-mailbox',
            str_contains($nombre, 'verbal') || str_contains($nombre, 'personal') => 'tabler-user',
            str_contains($nombre, 'web') || str_contains($nombre, 'portal') || str_contains($nombre, 'linea') => 'tabler-world',
            str_contains($nombre, 'telef') => 'tabler-phone',
            str_contains($nombre, 'fax') => 'tabler-printer',
            str_contains($nombre, 'mensaj') || str_contains($nombre, 'courier') || str_contains($nombre, 'postal') => 'tabler-truck-delivery',
            default => 'tabler-inbox',
        };
    }

    private function iconoTipoPqrs(string $nombre): string
    {
        $nombre = Str::lower(Str::ascii($nombre));

        return match (true) {
            str_contains($nombre, 'queja') => 'tabler-mood-sad',
            str_contains($nombre, 'reclamo') => 'tabler-alert-triangle',
            str_contains($nombre, 'sugerencia') => 'tabler-bulb',
            str_contains($nombre, 'felicita') => 'tabler-star',
            str_contains($nombre, 'denuncia') => 'tabler-shield-exclamation',
            str_contains($nombre, 'consulta') || str_contains($nombre, 'pregunta') => 'tabler-help-circle',
            str_contains($nombre, 'informacion') || str_contains($nombre, 'solicitud') => 'tabler-file-text',
            default => 'tabler-message',
        };
    }
}
