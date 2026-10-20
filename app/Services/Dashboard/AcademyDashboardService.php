<?php

namespace App\Services\Dashboard;

use App\Helpers\CalendarioHelper;
use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosResponsable;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoResponsable;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciResponsable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Dashboard Academy de la plantilla convertido en "Mi gestión": radicados asignados al funcionario
 * autenticado (como responsable), su avance y próximo vencimiento, más el uso de la TRD y los
 * funcionarios con más asignaciones dentro de su alcance ABAC.
 */
class AcademyDashboardService extends MetricasDashboard
{
    /** Módulos de radicación con su etiqueta y la tabla de responsables que los asigna. */
    private const MODULOS = [
        'recibidos' => ['etiqueta' => 'Recibidos', 'responsables' => VentanillaRadicaReciResponsable::class, 'fk' => 'radica_reci_id'],
        'enviados' => ['etiqueta' => 'Enviados', 'responsables' => VentanillaRadicaEnviadosResponsable::class, 'fk' => 'radica_enviado_id'],
        'internos' => ['etiqueta' => 'Internos', 'responsables' => VentanillaRadicaInternoResponsable::class, 'fk' => 'radica_interno_id'],
    ];

    private const COLORES = ['primary', 'info', 'success', 'secondary', 'error', 'warning'];

    /** @var Collection<int, ClasificacionDocumentalTRD>|null */
    private $trd = null;

    public function datos(User $user): array
    {
        $alcance = $this->alcance($user);
        $mios = $this->asignadosAlUsuario($alcance, $user);
        $resumen = $this->resumenPorModulo($mios);
        $usoTrd = $this->usoTrd($alcance);

        return [
            'welcome' => $this->bienvenida($user, $mios, $resumen),
            'topics' => $this->temas($usoTrd),
            'instructors' => $this->funcionariosDestacados($alcance),
            'top_courses' => $this->tiposDocumentales($usoTrd),
            'upcoming_webinar' => $this->proximoVencimiento($mios),
            'assignment_progress' => $this->avance($resumen),
            'courses_table' => $this->tablaSeries($usoTrd),
        ];
    }

    /**
     * Radicados de cada módulo en los que el usuario es responsable (las PQRS, a través de su radicado).
     *
     * @return array<string, Builder>
     */
    private function asignadosAlUsuario(array $alcance, User $user): array
    {
        $esResponsable = fn ($q) => $q->where('user_id', $user->id);

        return [
            'recibidos' => (clone $alcance['recibidos'])->whereHas('responsables.userCargo', $esResponsable),
            'enviados' => (clone $alcance['enviados'])->whereHas('responsables.userCargo', $esResponsable),
            'internos' => (clone $alcance['internos'])->whereHas('responsables.userCargo', $esResponsable),
            'pqrs' => (clone $alcance['pqrs'])->whereHas('radicado.responsables.userCargo', $esResponsable),
        ];
    }

    /** Restringe la consulta de un módulo a los radicados que ya no requieren gestión. */
    private function soloGestionados(string $modulo, Builder $query): Builder
    {
        return match ($modulo) {
            'pqrs' => $query->whereIn('estado_tramite', self::PQRS_ATENDIDAS),
            // Una comunicación enviada queda gestionada al firmarse
            'enviados' => $query->where(fn ($q) => $q->where('estado_firma', 'firmado')->orWhereIn('estado_trabajo', self::ESTADOS_CERRADOS)),
            default => $query->whereIn('estado_trabajo', self::ESTADOS_CERRADOS),
        };
    }

    /** Restringe la consulta de un módulo a los radicados que siguen abiertos. */
    private function soloAbiertos(string $modulo, Builder $query): Builder
    {
        return match ($modulo) {
            'pqrs' => $query->where(fn ($q) => $q->whereNull('estado_tramite')->orWhereNotIn('estado_tramite', self::PQRS_ATENDIDAS)),
            'enviados' => $query->where(fn ($q) => $q->whereNull('estado_firma')->orWhere('estado_firma', '!=', 'firmado'))
                ->where(fn ($q) => $q->whereNull('estado_trabajo')->orWhereNotIn('estado_trabajo', self::ESTADOS_CERRADOS)),
            default => $query->where(fn ($q) => $q->whereNull('estado_trabajo')->orWhereNotIn('estado_trabajo', self::ESTADOS_CERRADOS)),
        };
    }

    private function columnaVencimiento(string $modulo): string
    {
        return $modulo === 'pqrs' ? 'fecha_vencimiento' : 'fec_venci';
    }

    /**
     * Totales del usuario por módulo: asignados, gestionados, abiertos y vencidos.
     *
     * @return array<string, array{total: int, gestionados: int, abiertos: int, vencidos: int}>
     */
    private function resumenPorModulo(array $mios): array
    {
        $hoy = CalendarioHelper::hoy()->toDateString();
        $resumen = [];

        foreach ($mios as $modulo => $query) {
            $total = (clone $query)->count();
            $gestionados = $this->soloGestionados($modulo, clone $query)->count();
            $vencidos = $this->soloAbiertos($modulo, clone $query)->whereDate($this->columnaVencimiento($modulo), '<', $hoy)->count();
            $resumen[$modulo] = ['total' => $total, 'gestionados' => $gestionados, 'abiertos' => $total - $gestionados, 'vencidos' => $vencidos];
        }

        return $resumen;
    }

    private function bienvenida(User $user, array $mios, array $resumen): array
    {
        $total = array_sum(array_column($resumen, 'total'));
        $gestionados = array_sum(array_column($resumen, 'gestionados'));
        $abiertos = array_sum(array_column($resumen, 'abiertos'));
        $vencidos = array_sum(array_column($resumen, 'vencidos'));
        $porVencer = $this->porVencer($mios, 7);

        // Asignaciones recibidas este mes frente al anterior (fecha en que se asignó la responsabilidad)
        [$mesActual, $mesAnterior] = [0, 0];
        foreach (self::MODULOS as $modulo) {
            [$actual, $anterior] = $this->conteoMeses($modulo['responsables']::query()->whereHas('userCargo', fn ($q) => $q->where('user_id', $user->id)));
            $mesActual += $actual;
            $mesAnterior += $anterior;
        }
        $variacion = $this->variacion($mesActual, $mesAnterior);

        $etiquetas = ['Recibidos', 'Enviados', 'Internos', 'PQRS'];
        $series = array_map(fn ($r) => $this->porcentaje($r['total'], $total), array_values($resumen));

        return [
            'user_name' => trim((string) $user->nombres) ?: $this->nombreUsuario($user),
            'progress_text' => $total === 0
                ? 'Aún no tienes radicados asignados como responsable.'
                : "Tienes {$this->cantidad($abiertos, 'radicado', 'radicados')} por gestionar: {$this->cantidad($vencidos, 'vencido', 'vencidos')} y {$porVencer} con vencimiento en los próximos 7 días.",
            'items' => [
                ['title' => 'Pendientes', 'value' => (string) $abiertos, 'color' => 'primary'],
                ['title' => 'Cumplimiento', 'value' => $this->numero($this->porcentaje($gestionados, $total)).'%', 'color' => 'info'],
                ['title' => 'Gestionados', 'value' => (string) $gestionados, 'color' => 'warning'],
            ],
            'time_spendings' => [
                'labels' => $etiquetas,
                'donut_series' => $series,
                'total_hours' => (string) $total,
                'change' => $this->formatoVariacion($variacion),
                'trend' => $this->tendencia($variacion),
                'asignados_mes' => $mesActual,
            ],
        ];
    }

    /** Radicados abiertos del usuario que vencen entre hoy y los próximos $dias días. */
    private function porVencer(array $mios, int $dias): int
    {
        $hoy = CalendarioHelper::hoy();
        $total = 0;
        foreach ($mios as $modulo => $query) {
            $total += $this->soloAbiertos($modulo, clone $query)
                ->whereDate($this->columnaVencimiento($modulo), '>=', $hoy->toDateString())
                ->whereDate($this->columnaVencimiento($modulo), '<=', (clone $hoy)->addDays($dias)->toDateString())
                ->count();
        }

        return $total;
    }

    /**
     * Uso de la TRD en el alcance del usuario: radicados y gestionados por nodo de clasificación y módulo.
     * Las PQRS no se suman aparte porque su radicado recibido ya está contado.
     *
     * @return array<int, array{recibidos: int, enviados: int, internos: int, total: int, gestionados: int}>
     */
    private function usoTrd(array $alcance): array
    {
        $uso = [];
        foreach (array_keys(self::MODULOS) as $modulo) {
            $cerrados = $modulo === 'enviados'
                ? "estado_firma = 'firmado' OR estado_trabajo IN ('".implode("','", self::ESTADOS_CERRADOS)."')"
                : "estado_trabajo IN ('".implode("','", self::ESTADOS_CERRADOS)."')";

            $filas = (clone $alcance[$modulo])
                ->whereNotNull('clasifica_documen_id')
                ->selectRaw("clasifica_documen_id as nodo, COUNT(*) as total, SUM(CASE WHEN {$cerrados} THEN 1 ELSE 0 END) as gestionados")
                ->groupBy('clasifica_documen_id')
                ->toBase()
                ->get();

            foreach ($filas as $fila) {
                $uso[$fila->nodo] ??= ['recibidos' => 0, 'enviados' => 0, 'internos' => 0, 'total' => 0, 'gestionados' => 0];
                $uso[$fila->nodo][$modulo] += (int) $fila->total;
                $uso[$fila->nodo]['total'] += (int) $fila->total;
                $uso[$fila->nodo]['gestionados'] += (int) $fila->gestionados;
            }
        }

        return $uso;
    }

    /** Nodos de la TRD indexados por id (carga única). */
    private function trd()
    {
        return $this->trd ??= ClasificacionDocumentalTRD::query()
            ->get(['id', 'parent', 'tipo', 'cod', 'nom', 'dependencia_id'])
            ->keyBy('id');
    }

    /** Serie a la que pertenece un nodo de la TRD (sube por los padres hasta el tipo Serie). */
    private function serieDe(int $nodoId): ?ClasificacionDocumentalTRD
    {
        $nodo = $this->trd()->get($nodoId);
        $visitados = [];
        while ($nodo && $nodo->tipo !== 'Serie' && ! isset($visitados[$nodo->id])) {
            $visitados[$nodo->id] = true;
            $nodo = $nodo->parent ? $this->trd()->get($nodo->parent) : null;
        }

        return $nodo;
    }

    /**
     * Uso agrupado por serie documental, de mayor a menor.
     *
     * @return array<int, array{serie: ClasificacionDocumentalTRD, recibidos: int, enviados: int, internos: int, total: int, gestionados: int}>
     */
    private function usoPorSerie(array $usoTrd): array
    {
        $porSerie = [];
        foreach ($usoTrd as $nodoId => $uso) {
            $serie = $this->serieDe((int) $nodoId);
            if (! $serie) {
                continue;
            }
            // La misma serie se repite en cada versión de la TRD: se agrupa por dependencia, código y nombre
            $clave = $serie->dependencia_id.'|'.$serie->cod.'|'.$this->nombreNodo($serie);
            $porSerie[$clave] ??= ['serie' => $serie, 'recibidos' => 0, 'enviados' => 0, 'internos' => 0, 'total' => 0, 'gestionados' => 0];
            foreach (['recibidos', 'enviados', 'internos', 'total', 'gestionados'] as $campo) {
                $porSerie[$clave][$campo] += $uso[$campo];
            }
        }

        $porSerie = array_values($porSerie);
        usort($porSerie, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $porSerie;
    }

    /** Las 6 series documentales con más radicados, en porcentaje sobre el total clasificado. */
    private function temas(array $usoTrd): array
    {
        $porSerie = $this->usoPorSerie($usoTrd);
        $total = array_sum(array_column($porSerie, 'total'));
        $top = array_slice($porSerie, 0, 6);

        // Series homónimas de distintas dependencias se distinguen por su código
        $nombres = array_count_values(array_map(fn ($f) => $this->nombreNodo($f['serie']), $top));

        $items = [];
        foreach ($top as $i => $fila) {
            $nombre = $this->nombreNodo($fila['serie']);
            $items[] = [
                'title' => $nombres[$nombre] > 1 && $fila['serie']->cod ? "{$fila['serie']->cod} {$nombre}" : $nombre,
                'value' => $this->porcentaje($fila['total'], $total),
                'total' => $fila['total'],
                'colorClass' => 'text-'.self::COLORES[$i],
            ];
        }

        return [
            'series' => [['name' => 'Radicados', 'data' => array_column($items, 'value')]],
            'labels' => array_column($items, 'title'),
            'data1' => array_slice($items, 0, 3),
            'data2' => array_slice($items, 3, 3),
            'total' => $total,
        ];
    }

    /** Los 5 nodos de la TRD (normalmente tipos documentales) con más radicados en el alcance. */
    private function tiposDocumentales(array $usoTrd): array
    {
        uasort($usoTrd, fn ($a, $b) => $b['total'] <=> $a['total']);
        $iconos = ['tabler-file-text', 'tabler-file-certificate', 'tabler-file-invoice', 'tabler-file-description', 'tabler-files'];

        $resultado = [];
        foreach (array_slice($usoTrd, 0, 5, true) as $nodoId => $uso) {
            $nodo = $this->trd()->get($nodoId);
            if (! $nodo) {
                continue;
            }
            $serie = $this->serieDe((int) $nodoId);
            $resultado[] = [
                'title' => $this->nombreNodo($nodo),
                'serie' => $serie && $serie->id !== $nodo->id ? $this->nombreNodo($serie) : null,
                'views' => $uso['total'],
                'icon' => $iconos[count($resultado)],
                'color' => ['primary', 'info', 'success', 'warning', 'error'][count($resultado)],
            ];
        }

        return $resultado;
    }

    /**
     * Funcionarios con más radicados asignados como responsables dentro del alcance del usuario.
     */
    private function funcionariosDestacados(array $alcance): array
    {
        $porUsuario = [];
        foreach (self::MODULOS as $clave => $modulo) {
            $ids = (clone $alcance[$clave])->select($alcance[$clave]->getModel()->qualifyColumn('id'));
            $conteo = $modulo['responsables']::query()
                ->join('users_cargos', 'users_cargos.id', '=', 'users_cargos_id')
                ->whereIn($modulo['fk'], $ids)
                ->selectRaw("users_cargos.user_id, COUNT(DISTINCT {$modulo['fk']}) as total")
                ->groupBy('users_cargos.user_id')
                ->toBase()
                ->pluck('total', 'user_id');

            foreach ($conteo as $userId => $total) {
                $porUsuario[$userId] = ($porUsuario[$userId] ?? 0) + (int) $total;
            }
        }

        arsort($porUsuario);
        $top = array_slice($porUsuario, 0, 4, true);
        $usuarios = User::query()->with('cargoActivo.cargo')->whereIn('id', array_keys($top))->get()->keyBy('id');

        $resultado = [];
        foreach ($top as $userId => $total) {
            $usuario = $usuarios->get($userId);
            if (! $usuario) {
                continue;
            }
            $resultado[] = [
                'name' => $this->nombreUsuario($usuario),
                'profession' => $usuario->cargoActivo?->cargo?->nom_organico ?? 'Sin cargo activo',
                'totalCourses' => $total,
                'avatar' => $usuario->avatar_url,
            ];
        }

        return $resultado;
    }

    /** Radicado abierto del usuario con el vencimiento más cercano (de hoy en adelante). */
    private function proximoVencimiento(array $mios): array
    {
        $hoy = CalendarioHelper::hoy()->startOfDay();
        $proximo = null;

        foreach ($mios as $modulo => $query) {
            $columna = $this->columnaVencimiento($modulo);
            $registro = $this->soloAbiertos($modulo, clone $query)
                ->whereDate($columna, '>=', $hoy->toDateString())
                ->orderBy($columna)
                ->with($modulo === 'pqrs' ? ['radicado:id,num_radicado,asunto'] : [])
                ->first();

            if ($registro && (! $proximo || Carbon::parse($registro->{$columna})->lt($proximo['fecha']))) {
                $proximo = ['modulo' => $modulo, 'registro' => $registro, 'fecha' => Carbon::parse($registro->{$columna})->startOfDay()];
            }
        }

        if (! $proximo) {
            return [
                'title' => 'Sin vencimientos próximos',
                'desc' => 'No tienes radicados abiertos con vencimiento pendiente.',
                'date' => '—',
                'duration' => '—',
                'modulo' => null,
            ];
        }

        $registro = $proximo['registro'];
        $radicado = $proximo['modulo'] === 'pqrs' ? $registro->radicado : $registro;
        $dias = (int) $hoy->diffInDays($proximo['fecha']);
        $etiqueta = ['recibidos' => 'Radicado recibido', 'enviados' => 'Radicado enviado', 'internos' => 'Radicado interno', 'pqrs' => 'PQRS'][$proximo['modulo']];

        return [
            'title' => "{$etiqueta} ".($radicado?->num_radicado ?? "#{$registro->id}"),
            'desc' => Str::limit((string) ($radicado?->asunto ?: 'Sin asunto'), 140),
            'date' => $this->fechaCorta($proximo['fecha']),
            'duration' => match ($dias) {
                0 => 'Vence hoy',
                1 => '1 día',
                default => "{$dias} días",
            },
            'modulo' => $proximo['modulo'],
        ];
    }

    /** Avance del usuario por módulo: porcentaje gestionado sobre lo asignado. */
    private function avance(array $resumen): array
    {
        $titulos = [
            'recibidos' => 'Correspondencia recibida tramitada',
            'enviados' => 'Correspondencia enviada firmada',
            'internos' => 'Comunicaciones internas finalizadas',
            'pqrs' => 'PQRS respondidas',
        ];
        $colores = ['recibidos' => 'primary', 'enviados' => 'success', 'internos' => 'info', 'pqrs' => 'error'];

        $resultado = [];
        foreach ($resumen as $modulo => $r) {
            $resultado[] = [
                'title' => $titulos[$modulo],
                'tasks' => $r['total'],
                'pending' => $r['abiertos'],
                'progress' => (int) round($this->porcentaje($r['gestionados'], $r['total'])),
                'color' => $colores[$modulo],
                'modulo' => $modulo,
            ];
        }

        return $resultado;
    }

    /** Series documentales con radicados en el alcance: avance de gestión y reparto por módulo. */
    private function tablaSeries(array $usoTrd): array
    {
        $dependencias = CalidadOrganigrama::query()->pluck('nom_organico', 'id');
        $resultado = [];

        foreach ($this->usoPorSerie($usoTrd) as $i => $fila) {
            $serie = $fila['serie'];
            $resultado[] = [
                'id' => $serie->id,
                'courseTitle' => $this->nombreNodo($serie),
                'user' => $dependencias[$serie->dependencia_id] ?? 'Sin dependencia',
                'image' => null,
                'logo' => 'tabler-folders',
                'color' => self::COLORES[$i % count(self::COLORES)],
                'time' => $serie->cod ?: '—',
                'completedTasks' => $fila['gestionados'],
                'totalTasks' => $fila['total'],
                'userCount' => $fila['recibidos'],
                'note' => $fila['enviados'],
                'view' => $fila['internos'],
            ];
        }

        return $resultado;
    }

    private function cantidad(int $n, string $singular, string $plural): string
    {
        return $n.' '.($n === 1 ? $singular : $plural);
    }

    /** Nombre legible de un nodo TRD (sin los asteriscos de marcado que traen algunos tipos). */
    private function nombreNodo(ClasificacionDocumentalTRD $nodo): string
    {
        return trim(ltrim((string) $nodo->nom, '* ')) ?: (string) $nodo->cod;
    }
}
