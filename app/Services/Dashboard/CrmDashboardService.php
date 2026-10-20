<?php

namespace App\Services\Dashboard;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\UsersActivityLog;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\OfiArchivo\TiposDocumentalesExpediente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dashboard CRM de la plantilla alimentado con el flujo documental del alcance ABAC del usuario:
 * radicación diaria y mensual, carga por dependencia, producción por series TRD, completitud de
 * expedientes abiertos, últimos radicados y la actividad propia del usuario.
 */
class CrmDashboardService extends MetricasDashboard
{
    /** Tabla de responsables y llave del radicado, por tipo de radicado. */
    private const RESPONSABLES = [
        'recibidos' => ['ventanilla_radica_reci_responsa', 'radica_reci_id'],
        'enviados' => ['ventanilla_radica_enviados_responsable', 'radica_enviado_id'],
        'internos' => ['ventanilla_radica_internos_responsa', 'radica_interno_id'],
    ];

    private const ETIQUETAS_TIPO = ['recibidos' => 'Recibido', 'enviados' => 'Enviado', 'internos' => 'Interno'];

    /** Íconos y colores decorativos de la lista de dependencias (no son datos). */
    private const DECORACION = [
        ['tabler-building-bank', 'primary'],
        ['tabler-building-community', 'info'],
        ['tabler-scale', 'warning'],
        ['tabler-building', 'success'],
        ['tabler-briefcase', 'secondary'],
    ];

    public function datos(User $user): array
    {
        $alcance = $this->alcance($user);
        $radicados = array_intersect_key($alcance, self::ETIQUETAS_TIPO);

        return [
            'distributed_orders' => $this->radicacionDiaria($alcance['recibidos']),
            'yearly_sales' => $this->tramiteYCierre($radicados),
            'card_stats' => [
                'pqrs_activas' => $this->pqrsActivas($alcance['pqrs']),
                'firmas' => $this->firmasDelMes($radicados),
            ],
            'revenue_growth' => $this->flujoSemanal($radicados),
            'tab_data' => $this->balancePorModulo($alcance),
            'radar_data' => $this->canales($alcance['recibidos'], $alcance['enviados']),
            'dependencias' => $this->cargaPorDependencia($radicados),
            'project_status' => $this->produccionPorSeries($radicados),
            'active_projects' => $this->expedientesAbiertos($user),
            'last_transactions' => $this->ultimosRadicados($radicados),
            'activity_timeline' => $this->actividad($user),
        ];
    }

    /** Recibidos por día de los últimos 7 días y variación frente a los 7 anteriores. */
    private function radicacionDiaria(Builder $recibidos): array
    {
        [$actual, $anterior] = $this->conteoPeriodos($recibidos, 7);
        $variacion = $this->variacion($actual, $anterior);

        return [
            'series' => [['name' => 'Recibidos', 'data' => $this->conteoPorDia($recibidos, 7)]],
            'total' => $actual,
            'trend' => $this->formatoVariacion($variacion),
            'trend_status' => $this->tendencia($variacion),
        ];
    }

    /**
     * Radicados ya cerrados (finalizados, tramitados o anulados) por mes de radicación en los
     * últimos 4 meses; la variación compara el mes en curso con el anterior.
     *
     * @param  array<string, Builder>  $radicados
     */
    private function tramiteYCierre(array $radicados): array
    {
        $serie = array_fill(0, 4, 0);
        foreach ($radicados as $query) {
            $cerrados = (clone $query)->whereIn('estado_trabajo', self::ESTADOS_CERRADOS);
            foreach ($this->conteoPorMes($cerrados, 4) as $i => $total) {
                $serie[$i] += $total;
            }
        }
        $variacion = $this->variacion($serie[3], $serie[2]);

        return [
            'series' => [['name' => 'Cerrados', 'data' => $serie]],
            'categories' => $this->etiquetasMeses(4),
            'total' => array_sum($serie),
            'trend' => $this->formatoVariacion($variacion),
            'trend_status' => $this->tendencia($variacion),
        ];
    }

    private function pqrsActivas(Builder $pqrs): array
    {
        $abiertas = (clone $pqrs)->whereNotIn('estado_tramite', self::PQRS_ATENDIDAS);
        $total = (clone $abiertas)->count();
        $vencidas = (clone $abiertas)->where(fn ($q) => $q->where('estado_tramite', 'Vencida')
            ->orWhere('fecha_vencimiento', '<', now()->startOfDay()))->count();

        return [
            'title' => 'PQRSF Activas',
            'subtitle' => $vencidas > 0 ? 'Con términos vencidos' : 'En Término Legal',
            'stats' => (string) $total,
            'chipText' => $vencidas > 0 ? "{$vencidas} vencidas" : 'Al día',
            'chipColor' => $vencidas > 0 ? 'error' : 'success',
        ];
    }

    /**
     * Firmas registradas este mes sobre radicados del alcance del usuario, frente al mes anterior.
     *
     * @param  array<string, Builder>  $radicados
     */
    private function firmasDelMes(array $radicados): array
    {
        $modelos = [
            'recibidos' => VentanillaRadicaReci::class,
            'enviados' => VentanillaRadicaEnviados::class,
            'internos' => VentanillaRadicaInterno::class,
        ];
        $firmas = FirmaEvento::query()->where(function ($q) use ($radicados, $modelos) {
            foreach ($radicados as $tipo => $query) {
                $q->orWhere(fn ($w) => $w->where('documentable_type', $modelos[$tipo])
                    ->whereIn('documentable_id', (clone $query)->select($query->getModel()->getQualifiedKeyName())));
            }
        });
        [$actual, $anterior] = $this->conteoMeses($firmas, 'fecha_firma');
        $variacion = $this->variacion($actual, $anterior);

        return [
            'title' => 'Firmas Digitales',
            'subtitle' => 'Mes en Curso',
            'stats' => (string) $actual,
            'chipText' => $this->formatoVariacion($variacion),
            'chipColor' => $variacion < 0 ? 'error' : 'success',
        ];
    }

    /**
     * Radicados de todos los tipos por día de la semana en curso (últimos 7 días).
     *
     * @param  array<string, Builder>  $radicados
     */
    private function flujoSemanal(array $radicados): array
    {
        $serie = array_fill(0, 7, 0);
        $actual = $anterior = 0;
        foreach ($radicados as $query) {
            foreach ($this->conteoPorDia($query, 7) as $i => $total) {
                $serie[$i] += $total;
            }
            [$a, $b] = $this->conteoPeriodos($query, 7);
            $actual += $a;
            $anterior += $b;
        }
        $variacion = $this->variacion($actual, $anterior);

        return [
            'series' => [['name' => 'Radicados', 'data' => $serie]],
            'categories' => $this->etiquetasDias(7),
            'total' => $actual,
            'trend' => $this->formatoVariacion($variacion),
            'trend_status' => $this->tendencia($variacion),
        ];
    }

    /**
     * Radicados por mes (últimos 9 meses) de cada módulo.
     *
     * @param  array<string, Builder>  $alcance
     */
    private function balancePorModulo(array $alcance): array
    {
        $tabs = [
            'recibidos' => ['Recibidos', 'tabler-mail'],
            'enviados' => ['Enviados', 'tabler-send'],
            'internos' => ['Internos', 'tabler-arrows-left-right'],
            'pqrs' => ['PQRSF', 'tabler-help-circle'],
        ];
        $categorias = $this->etiquetasMeses(9);

        return collect($tabs)->map(fn ($tab, $tipo) => [
            'type' => $tipo,
            'label' => $tab[0],
            'avatarIcon' => $tab[1],
            'categories' => $categorias,
            'series' => [['name' => $tab[0], 'data' => $this->conteoPorMes($alcance[$tipo], 9)]],
        ])->values()->all();
    }

    /** Recibidos por medio de recepción y enviados por medio de envío, últimos 6 meses. */
    private function canales(Builder $recibidos, Builder $enviados): array
    {
        $medios = ConfigListaDetalle::query()
            ->whereHas('lista', fn ($q) => $q->where('cod', 'TipRecep'))
            ->orderBy('nombre')
            ->pluck('nombre', 'id');
        $desde = now()->startOfMonth()->subMonths(5);
        $porMedio = fn (Builder $query, string $columna) => (clone $query)
            ->where('created_at', '>=', $desde)
            ->whereNotNull($columna)
            ->selectRaw("{$columna} as medio, COUNT(*) as total")
            ->groupBy('medio')
            ->pluck('total', 'medio');
        $entradas = $porMedio($recibidos, 'medio_recep_id');
        $salidas = $porMedio($enviados, 'medio_enviado_id');

        return [
            'categories' => $medios->values()->all(),
            'series' => [
                ['name' => 'Recibidos', 'data' => $medios->keys()->map(fn ($id) => (int) ($entradas[$id] ?? 0))->all()],
                ['name' => 'Enviados', 'data' => $medios->keys()->map(fn ($id) => (int) ($salidas[$id] ?? 0))->all()],
            ],
        ];
    }

    /**
     * Radicados activos (sin cerrar) asignados a cada dependencia, con la variación de lo
     * asignado este mes frente al mes anterior. Cada radicado cuenta una vez por dependencia.
     *
     * @param  array<string, Builder>  $radicados
     */
    private function cargaPorDependencia(array $radicados): array
    {
        $inicioMes = now()->startOfMonth();
        $inicioAnterior = (clone $inicioMes)->subMonth();
        $porDependencia = [];

        foreach ($radicados as $tipo => $query) {
            [$tablaResponsables, $llave] = self::RESPONSABLES[$tipo];
            $modelo = $query->getModel();
            $filas = DB::table("{$tablaResponsables} as r")
                ->join('users_cargos as uc', 'uc.id', '=', 'r.users_cargos_id')
                ->join($modelo->getTable().' as d', 'd.id', '=', "r.{$llave}")
                ->whereIn("r.{$llave}", (clone $query)->select($modelo->getQualifiedKeyName()))
                ->where(fn ($q) => $q->whereNull('d.estado_trabajo')
                    ->orWhereNotIn('d.estado_trabajo', self::ESTADOS_CERRADOS)
                    ->orWhere('d.created_at', '>=', $inicioAnterior))
                ->distinct()
                ->get(['d.id', 'd.estado_trabajo', 'd.created_at', 'uc.cargo_id']);

            foreach ($filas as $fila) {
                $dependencia = $this->dependenciaDeNodo((int) $fila->cargo_id);
                if (! $dependencia) {
                    continue;
                }
                $porDependencia[$dependencia['id']] ??= $dependencia + ['activos' => [], 'actual' => [], 'anterior' => []];
                $clave = "{$tipo}-{$fila->id}";
                $creado = Carbon::parse($fila->created_at);
                if (! in_array($fila->estado_trabajo, self::ESTADOS_CERRADOS, true)) {
                    $porDependencia[$dependencia['id']]['activos'][$clave] = true;
                }
                if ($creado->gte($inicioMes)) {
                    $porDependencia[$dependencia['id']]['actual'][$clave] = true;
                } elseif ($creado->gte($inicioAnterior)) {
                    $porDependencia[$dependencia['id']]['anterior'][$clave] = true;
                }
            }
        }

        return collect($porDependencia)
            ->sortByDesc(fn ($d) => [count($d['activos']), count($d['actual'])])
            ->take(count(self::DECORACION))
            ->values()
            ->map(function ($d, $i) {
                $variacion = $this->variacion(count($d['actual']), count($d['anterior']));

                return [
                    'title' => (string) count($d['activos']),
                    'subtitle' => $d['nombre'],
                    'trendNumber' => abs($variacion),
                    'trend' => $this->tendencia($variacion),
                    'icon' => self::DECORACION[$i][0],
                    'avatarColor' => self::DECORACION[$i][1],
                ];
            })
            ->all();
    }

    /**
     * Radicados de los últimos 12 meses agrupados por la serie TRD de su clasificación, con la
     * serie mensual del total y la variación del mes en curso frente al anterior.
     *
     * @param  array<string, Builder>  $radicados
     */
    private function produccionPorSeries(array $radicados): array
    {
        $inicioMes = now()->startOfMonth();
        $mensual = array_fill(0, 12, 0);
        $porSerie = [];
        $nodos = ClasificacionDocumentalTRD::query()->get(['id', 'tipo', 'nom', 'parent'])->keyBy('id');

        foreach ($radicados as $query) {
            foreach ($this->conteoPorMes($query, 12) as $i => $total) {
                $mensual[$i] += $total;
            }

            $conteos = (clone $query)
                ->where('created_at', '>=', (clone $inicioMes)->subMonths(11))
                ->whereNotNull('clasifica_documen_id')
                ->selectRaw('clasifica_documen_id, COUNT(*) as total')
                ->selectRaw('SUM(created_at >= ?) as actual', [$inicioMes])
                ->selectRaw('SUM(created_at >= ? AND created_at < ?) as anterior', [(clone $inicioMes)->subMonth(), $inicioMes])
                ->groupBy('clasifica_documen_id')
                ->get();

            foreach ($conteos as $fila) {
                $serie = $this->serieDeNodo($nodos, (int) $fila->clasifica_documen_id);
                if (! $serie) {
                    continue;
                }
                $porSerie[$serie->id] ??= ['nombre' => $serie->nom, 'total' => 0, 'actual' => 0, 'anterior' => 0];
                foreach (['total', 'actual', 'anterior'] as $campo) {
                    $porSerie[$serie->id][$campo] += (int) $fila->{$campo};
                }
            }
        }

        $variacion = $this->variacion($mensual[11], $mensual[10]);

        return [
            'total_docs' => array_sum($mensual),
            'trend' => $this->formatoVariacion($variacion),
            'trend_status' => $this->tendencia($variacion),
            'categories' => $this->etiquetasMeses(12),
            'series' => [['name' => 'Radicados', 'data' => $mensual]],
            'items' => collect($porSerie)->sortByDesc('total')->take(3)->values()->map(function ($s) {
                $variacion = $this->variacion($s['actual'], $s['anterior']);

                return [
                    'title' => $s['nombre'],
                    'amount' => "{$s['total']} docs",
                    'trendDiff' => abs($variacion),
                    'trend' => $this->tendencia($variacion),
                ];
            })->all(),
        ];
    }

    /** Serie TRD a la que pertenece un nodo (subserie o tipo documental), subiendo por el árbol. */
    private function serieDeNodo($nodos, int $id): ?ClasificacionDocumentalTRD
    {
        $nodo = $nodos->get($id);
        $visitados = [];
        while ($nodo && $nodo->tipo !== 'Serie' && ! isset($visitados[$nodo->id])) {
            $visitados[$nodo->id] = true;
            $nodo = $nodo->parent ? $nodos->get($nodo->parent) : null;
        }

        return $nodo;
    }

    /**
     * Expedientes abiertos visibles para el usuario, con su completitud documental: porcentaje de
     * los tipos documentales que la TRD define para su subserie (o serie) que ya tienen al menos
     * un documento activo en el expediente.
     */
    private function expedientesAbiertos(User $user): array
    {
        $expedientes = OfiArchivoExpediente::query()
            ->visiblesPara($user)
            ->where('estado', 'abierto')
            ->latest('fecha_apertura')
            ->limit(4)
            ->get(['id', 'numero_expediente', 'nombre_expediente', 'serie_trd_id', 'subserie_trd_id']);
        if ($expedientes->isEmpty()) {
            return [];
        }

        $nodos = $expedientes->map(fn ($e) => $e->subserie_trd_id ?: $e->serie_trd_id)->filter()->unique();
        $catalogo = ClasificacionDocumentalTRD::query()
            ->where('tipo', 'TipoDocumento')
            ->whereIn('parent', $nodos)
            ->get(['id', 'parent'])
            ->groupBy('parent');
        $presentes = TiposDocumentalesExpediente::disponible()
            ? OfiArchivoExpedienteDocumento::query()
                ->whereIn('expediente_id', $expedientes->pluck('id'))
                ->where('activo', true)
                ->whereNotNull('tipo_documental_id')
                ->distinct()
                ->get(['expediente_id', 'tipo_documental_id'])
                ->groupBy('expediente_id')
            : collect();

        return $expedientes->map(function ($expediente) use ($catalogo, $presentes) {
            $tipos = ($catalogo[$expediente->subserie_trd_id ?: $expediente->serie_trd_id] ?? collect())->pluck('id');
            $cubiertos = ($presentes[$expediente->id] ?? collect())->pluck('tipo_documental_id')->intersect($tipos)->count();
            $progreso = (int) round($this->porcentaje($cubiertos, $tipos->count()));

            return [
                'title' => $expediente->nombre_expediente,
                'subtitle' => $expediente->numero_expediente,
                'progress' => $progreso,
                'progressColor' => $progreso >= 100 ? 'success' : ($progreso >= 50 ? 'primary' : 'warning'),
                'icon' => 'tabler-folder',
            ];
        })->all();
    }

    /**
     * Últimos radicados del alcance del usuario, de todos los tipos.
     *
     * @param  array<string, Builder>  $radicados
     */
    private function ultimosRadicados(array $radicados): array
    {
        $estados = [
            'RECIBIDO' => ['pending', 'Recibido'],
            'EN_PROCESO' => ['on-hold', 'En Proceso'],
            'POR_VENCER' => ['on-hold', 'Por Vencer'],
            'VENCIDO' => ['rejected', 'Vencido'],
            'TRAMITADO' => ['verified', 'Tramitado'],
            'FINALIZADO' => ['verified', 'Finalizado'],
            'ANULADO' => ['rejected', 'Anulado'],
        ];

        return collect($radicados)
            ->flatMap(fn (Builder $query, $tipo) => (clone $query)
                ->latest('created_at')
                ->limit(6)
                ->get(['num_radicado', 'asunto', 'estado_trabajo', 'created_at'])
                ->map(fn ($r) => ['tipo' => self::ETIQUETAS_TIPO[$tipo], 'registro' => $r]))
            ->sortByDesc(fn ($fila) => $fila['registro']->created_at)
            ->take(6)
            ->map(function ($fila) use ($estados) {
                $r = $fila['registro'];
                [$status, $estado] = $estados[$r->estado_trabajo ?? 'RECIBIDO'] ?? ['pending', Str::title((string) $r->estado_trabajo)];

                return [
                    'radicado' => $r->num_radicado,
                    'tipo' => $fila['tipo'],
                    'asunto' => Str::limit((string) $r->asunto, 60),
                    'date' => $this->fechaCorta($r->created_at),
                    'status' => $status,
                    'estado' => $estado,
                ];
            })
            ->values()
            ->all();
    }

    /** Acciones recientes del propio usuario (bitácora de actividad y firmas), sin las consultas. */
    private function actividad(User $user): array
    {
        $acciones = [
            'create' => ['Registro', 'success'], 'created' => ['Registro', 'success'],
            'update' => ['Actualización', 'info'], 'updated' => ['Actualización', 'info'],
            'delete' => ['Eliminación', 'error'], 'deleted' => ['Eliminación', 'error'],
        ];

        $logs = UsersActivityLog::query()
            ->where('user_id', $user->id)
            ->where('action', '!=', 'view')
            ->latest('id')
            ->limit(6)
            ->get(['module', 'action', 'description', 'created_at'])
            ->map(fn ($log) => [
                'title' => trim($log->module.' · '.($acciones[$log->action][0] ?? Str::ucfirst($log->action))),
                'subtitle' => (string) $log->description,
                'color' => $acciones[$log->action][1] ?? 'primary',
                'fecha' => $log->created_at,
            ]);

        $firmas = FirmaEvento::query()
            ->where('user_id', $user->id)
            ->latest('fecha_firma')
            ->limit(6)
            ->with('documentable')
            ->get()
            ->map(fn ($firma) => [
                'title' => 'Documento firmado',
                'subtitle' => 'Firma electrónica'.($firma->documentable?->num_radicado ? " del radicado {$firma->documentable->num_radicado}" : ''),
                'color' => 'warning',
                'fecha' => $firma->fecha_firma,
            ]);

        return $logs->concat($firmas)
            ->sortByDesc(fn ($e) => Carbon::parse($e['fecha']))
            ->take(6)
            ->map(fn ($e) => [
                'title' => $e['title'],
                'subtitle' => $e['subtitle'],
                'color' => $e['color'],
                'time' => Carbon::parse($e['fecha'])->locale('es')->diffForHumans(),
            ])
            ->values()
            ->all();
    }
}
