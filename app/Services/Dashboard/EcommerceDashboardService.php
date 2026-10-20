<?php

namespace App\Services\Dashboard;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoExpedienteEvento;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\OfiArchivo\OfiArchivoTransferencia;
use App\Models\User;
use App\Services\OfiArchivo\PrestamosService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dashboard eCommerce de la plantilla alimentado con la gestión de archivo: expedientes,
 * documentos incorporados, préstamos, transferencias e historial de los expedientes.
 *
 * Alcance: los expedientes (y lo que cuelga de ellos) se limitan a los visibles para el
 * usuario (M08-03); los préstamos, a los propios salvo con el permiso de verlos todos.
 */
class EcommerceDashboardService extends MetricasDashboard
{
    private const FASES = ['gestion' => 'Gestión', 'central' => 'Central', 'historico' => 'Histórico'];

    /** Acción del historial del expediente → [etiqueta, ícono, color]. */
    private const ACCIONES = [
        'apertura' => ['Apertura', 'tabler-folder-plus', 'primary'],
        'edicion' => ['Edición', 'tabler-pencil', 'secondary'],
        'cierre' => ['Cierre', 'tabler-folder-check', 'success'],
        'reapertura' => ['Reapertura', 'tabler-folder-open', 'warning'],
        'incorporacion' => ['Documento incorporado', 'tabler-file-plus', 'success'],
        'retiro' => ['Documento retirado', 'tabler-file-minus', 'error'],
        'tipificacion' => ['Tipificación', 'tabler-tags', 'info'],
        'prestamo' => ['Préstamo', 'tabler-arrow-up-right', 'info'],
        'devolucion' => ['Devolución', 'tabler-arrow-back-up', 'success'],
        'prestamo_vencido' => ['Préstamo vencido', 'tabler-alert-triangle', 'error'],
        'envio_transferencia' => ['Envío a transferencia', 'tabler-truck-delivery', 'info'],
        'transferencia' => ['Transferencia', 'tabler-building-warehouse', 'success'],
        'transferencia_devuelta' => ['Transferencia devuelta', 'tabler-truck-return', 'error'],
        'inicio_disposicion' => ['Inicio de disposición', 'tabler-hourglass', 'warning'],
        'eliminacion' => ['Eliminación', 'tabler-trash', 'error'],
        'indice_firmado' => ['Índice firmado', 'tabler-signature', 'primary'],
        'hoja_control_firmada' => ['Hoja de control firmada', 'tabler-signature', 'primary'],
        'vinculo_tercero' => ['Tercero vinculado', 'tabler-user-plus', 'info'],
        'desvinculo_tercero' => ['Tercero desvinculado', 'tabler-user-minus', 'error'],
    ];

    public function datos(User $user): array
    {
        $expedientes = $this->expedientes($user);
        $documentos = $this->documentos($user);
        $prestamos = $this->prestamos($user);

        return [
            'bienvenida' => $this->bienvenida($user),
            'estadisticas' => [
                'expedientes' => (clone $expedientes)->count(),
                'prestamos_activos' => (clone $prestamos)->whereIn('estado', OfiArchivoPrestamo::ESTADOS_ACTIVOS)->count(),
                'transferencias_pendientes' => OfiArchivoTransferencia::where('estado', 'pendiente')
                    ->whereIn('expediente_id', (clone $expedientes)->select('id'))->count(),
                'documentos' => (clone $documentos)->count(),
                'actualizado' => now()->setTimezone(config('app.timezone_local'))->format('h:i a'),
            ],
            'documentos_mes' => $this->documentosMes($documentos),
            'expedientes_cerrados' => $this->expedientesCerrados($expedientes),
            'fases' => $this->fases($expedientes),
            'reporte_anual' => $this->reporteAnual($expedientes),
            'prestamos_semana' => $this->prestamosSemana($prestamos),
            'series_trd' => $this->seriesTrd($expedientes),
            'prestamos_por_estado' => $this->prestamosPorEstado($prestamos),
            'movimientos' => $this->movimientos($expedientes),
            'expedientes' => $this->listaExpedientes($expedientes),
        ];
    }

    /** Expedientes visibles para el usuario, sin los eliminados por disposición final. */
    private function expedientes(User $user): Builder
    {
        return OfiArchivoExpediente::query()->visiblesPara($user)->where('estado', '!=', 'eliminado');
    }

    private function documentos(User $user): Builder
    {
        return OfiArchivoExpedienteDocumento::query()->where('activo', true)
            ->whereIn('expediente_id', $this->expedientes($user)->select('id'));
    }

    /** Préstamos que el usuario puede consultar: todos con el permiso Ver; si no, los que solicitó. */
    private function prestamos(User $user): Builder
    {
        $query = OfiArchivoPrestamo::query();

        return PrestamosService::tiene($user, PrestamosService::PERMISO_VER)
            ? $query
            : $query->where('solicitante_id', $user->id);
    }

    private function bienvenida(User $user): array
    {
        $inicioMes = now()->startOfMonth();

        return [
            'nombre' => trim(explode(' ', trim((string) $user->nombres))[0] ?? '') ?: $this->nombreUsuario($user),
            'documentos_mes' => OfiArchivoExpedienteDocumento::where('usuario_id', $user->id)->where('activo', true)
                ->where('fecha_incorporacion', '>=', $inicioMes)->count(),
            'expedientes_mes' => OfiArchivoExpediente::where('usuario_apertura_id', $user->id)
                ->where('fecha_apertura', '>=', $inicioMes)->count(),
        ];
    }

    private function documentosMes(Builder $documentos): array
    {
        [$actual, $anterior] = $this->conteoMeses($documentos, 'fecha_incorporacion');
        $variacion = $this->variacion($actual, $anterior);

        return [
            'total' => $actual,
            'serie' => $this->conteoPorMes($documentos, 6, 'fecha_incorporacion'),
            'variacion' => $this->formatoVariacion($variacion),
            'tendencia' => $this->tendencia($variacion),
        ];
    }

    private function expedientesCerrados(Builder $expedientes): array
    {
        $total = (clone $expedientes)->count();
        $cerrados = (clone $expedientes)->where('estado', '!=', 'abierto')->count();
        [$mes, $mesAnterior] = $this->conteoMeses($expedientes, 'fecha_cierre');
        $diferencia = $mes - $mesAnterior;

        return [
            'cerrados' => $cerrados,
            'total' => $total,
            'porcentaje' => $this->porcentaje($cerrados, $total),
            'cierres_mes' => $mes,
            'texto' => $diferencia === 0
                ? 'Los mismos cierres que el mes pasado'
                : abs($diferencia).' cierre'.(abs($diferencia) === 1 ? '' : 's')
                    .($diferencia > 0 ? ' más' : ' menos').' que el mes pasado',
        ];
    }

    private function fases(Builder $expedientes): array
    {
        $porFase = (clone $expedientes)->selectRaw('fase, COUNT(*) as total')->groupBy('fase')->pluck('total', 'fase');
        [$actual, $anterior] = $this->conteoMeses($expedientes, 'fecha_apertura');
        $variacion = $this->variacion($actual, $anterior);

        return [
            'labels' => array_values(self::FASES),
            'series' => array_map(fn ($fase) => (int) ($porFase[$fase] ?? 0), array_keys(self::FASES)),
            'total' => (int) $porFase->sum(),
            'variacion' => $this->formatoVariacion($variacion),
            'tendencia' => $this->tendencia($variacion),
        ];
    }

    /**
     * Aperturas y cierres por mes de cada año con expedientes (hasta 4, del más reciente al más
     * antiguo), con las aperturas del año anterior para comparar.
     */
    private function reporteAnual(Builder $expedientes): array
    {
        $anioActual = now()->year;
        $primero = (clone $expedientes)->min('fecha_apertura');
        $desde = max($primero ? Carbon::parse($primero)->year : $anioActual, $anioActual - 3);

        $aperturas = $this->conteoPorAnioMes($expedientes, 'fecha_apertura', $desde - 1);
        $cierres = $this->conteoPorAnioMes($expedientes, 'fecha_cierre', $desde);
        $categorias = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $categorias[] = mb_convert_case(rtrim(Carbon::create(2000, $mes, 1)->locale('es')->isoFormat('MMM'), '.'), MB_CASE_TITLE);
        }

        $anios = [];
        for ($anio = $anioActual; $anio >= $desde; $anio--) {
            $abiertos = $this->mesesDelAnio($aperturas, $anio);
            $cerrados = $this->mesesDelAnio($cierres, $anio);
            $anios[] = [
                'anio' => $anio,
                'abiertos' => $abiertos,
                'cerrados' => $cerrados,
                'abiertos_anterior' => $this->mesesDelAnio($aperturas, $anio - 1),
                'total_abiertos' => array_sum($abiertos),
                'total_cerrados' => array_sum($cerrados),
            ];
        }

        return ['categorias' => $categorias, 'anios' => $anios];
    }

    /** @return Collection<string, int> conteos por 'Y-m' desde el 1 de enero de $desde */
    private function conteoPorAnioMes(Builder $query, string $columna, int $desde)
    {
        return (clone $query)
            ->where($columna, '>=', Carbon::create($desde, 1, 1))
            ->selectRaw("DATE_FORMAT({$columna}, '%Y-%m') as mes, COUNT(*) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');
    }

    private function mesesDelAnio($conteos, int $anio): array
    {
        $serie = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $serie[] = (int) ($conteos[sprintf('%d-%02d', $anio, $mes)] ?? 0);
        }

        return $serie;
    }

    private function prestamosSemana(Builder $prestamos): array
    {
        // Vencidos: préstamos que salieron del archivo y no volvieron en la fecha esperada (§3.10:
        // el acceso electrónico no vence)
        $vencidos = (clone $prestamos)->where('modalidad', '!=', 'electronico')->where(fn ($q) => $q
            ->where(fn ($q) => $q->whereNull('fecha_devolucion_real')->where('fecha_devolucion_esperada', '<', now()))
            ->orWhereColumn('fecha_devolucion_real', '>', 'fecha_devolucion_esperada'));

        $indicadores = [
            ['Préstamos', 'Registrados en 7 días', 'primary', 'tabler-books', $prestamos, 'fecha_prestamo'],
            ['Devoluciones', 'Recibidas en 7 días', 'success', 'tabler-arrow-back-up', $prestamos, 'fecha_devolucion_real'],
            ['Vencidos', 'Sin devolver a tiempo', 'error', 'tabler-alert-triangle', $vencidos, 'fecha_devolucion_esperada'],
        ];

        $items = [];
        foreach ($indicadores as [$titulo, $subtitulo, $color, $icono, $query, $columna]) {
            [$actual, $anterior] = $this->conteoPeriodos($query, 7, $columna);
            $variacion = $this->variacion($actual, $anterior);
            $items[] = [
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'valor' => $actual,
                'variacion' => $this->numero(abs($variacion)),
                'tendencia' => $this->tendencia($variacion),
                'color' => $color,
                'icono' => $icono,
            ];
        }

        return [
            'items' => $items,
            'serie' => $this->conteoPorDia($prestamos, 7, 'fecha_prestamo'),
            'categorias' => $this->etiquetasDias(7),
        ];
    }

    private function seriesTrd(Builder $expedientes): array
    {
        $conteo = (clone $expedientes)->whereNotNull('serie_trd_id')
            ->selectRaw('serie_trd_id, COUNT(*) as total')->groupBy('serie_trd_id')
            ->orderByDesc('total')->limit(6)->pluck('total', 'serie_trd_id');
        $series = ClasificacionDocumentalTRD::whereIn('id', $conteo->keys())->get(['id', 'cod', 'nom'])->keyBy('id');

        return [
            'total' => (clone $expedientes)->count(),
            'items' => $conteo->map(fn ($total, $id) => [
                'id' => (int) $id,
                'nombre' => $series[$id]->nom ?? 'Serie sin nombre',
                'codigo' => $series[$id]->cod ?? '—',
                'total' => (int) $total,
            ])->values()->all(),
        ];
    }

    private function prestamosPorEstado(Builder $prestamos): array
    {
        $grupos = [
            'pendientes' => (clone $prestamos)->whereIn('estado', OfiArchivoPrestamo::ESTADOS_PENDIENTES),
            'prestados' => (clone $prestamos)->where('estado', 'prestado')->where('fecha_devolucion_esperada', '>=', now()),
            'vencidos' => (clone $prestamos)->vencidos(),
        ];

        $resultado = ['en_curso' => (clone $prestamos)->whereIn('estado', OfiArchivoPrestamo::ESTADOS_ACTIVOS)->count()];
        foreach ($grupos as $clave => $query) {
            $resultado[$clave] = [
                'total' => (clone $query)->count(),
                'items' => (clone $query)->with(['solicitante:id,nombres,apellidos,email', 'expediente:id,numero_expediente,nombre_expediente'])
                    ->latest()->orderByDesc('id')->limit(3)->get()
                    ->map(fn (OfiArchivoPrestamo $p) => [
                        'id' => $p->id,
                        'solicitante' => $this->nombreUsuario($p->solicitante),
                        'dependencia' => $p->dependencia_destino ?: 'Sin dependencia destino',
                        'expediente' => $p->expediente->numero_expediente ?? 'Expediente no disponible',
                        'detalle' => $p->fecha_devolucion_esperada
                            ? 'Devolución: '.$this->fechaCorta($p->fecha_devolucion_esperada)
                            : ($p->expediente->nombre_expediente ?? ''),
                    ])->all(),
            ];
        }

        return $resultado;
    }

    private function movimientos(Builder $expedientes): array
    {
        $eventos = OfiArchivoExpedienteEvento::query()->whereIn('expediente_id', (clone $expedientes)->select('id'));

        return [
            'total_mes' => (clone $eventos)->where('ocurrido_en', '>=', now()->startOfMonth())->count(),
            'items' => (clone $eventos)->with(['expediente:id,numero_expediente', 'usuario:id,nombres,apellidos,email'])
                ->orderByDesc('ocurrido_en')->orderByDesc('id')->limit(7)->get()
                ->map(function (OfiArchivoExpedienteEvento $evento) {
                    [$titulo, $icono, $color] = self::ACCIONES[$evento->accion]
                        ?? [ucfirst(str_replace('_', ' ', $evento->accion)), 'tabler-history', 'secondary'];

                    return [
                        'id' => $evento->id,
                        'titulo' => $titulo,
                        'subtitulo' => ($evento->expediente->numero_expediente ?? 'Expediente')
                            .($evento->usuario ? ' · '.$this->nombreUsuario($evento->usuario) : ''),
                        'cuando' => $evento->ocurrido_en?->locale('es')->diffForHumans(),
                        'icono' => $icono,
                        'color' => $color,
                        'negativo' => $color === 'error',
                    ];
                })->all(),
        ];
    }

    private function listaExpedientes(Builder $expedientes): array
    {
        return (clone $expedientes)
            ->orderByDesc('fecha_apertura')->orderByDesc('id')->limit(50)
            ->get(['id', 'numero_expediente', 'nombre_expediente', 'estado', 'fase', 'dependencia_nom', 'fecha_apertura', 'total_folios_elec'])
            ->map(fn (OfiArchivoExpediente $e) => [
                'id' => $e->id,
                'numero' => $e->numero_expediente,
                'nombre' => $e->nombre_expediente,
                'estado' => $e->estado,
                'fase' => self::FASES[$e->fase] ?? $e->fase,
                'dependencia' => $e->dependencia_nom,
                'apertura' => $e->fecha_apertura?->format('d/m/Y'),
                'folios' => (int) $e->total_folios_elec,
            ])->all();
    }
}
