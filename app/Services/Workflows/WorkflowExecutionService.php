<?php

namespace App\Services\Workflows;

use App\Exceptions\Workflows\StateTransitionException;
use App\Models\User;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsOptimizedView;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowConexion;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;
use App\Models\Workflows\WorkflowNodoInstancia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Motor de ejecución de Workflows — Máquina de estados.
 *
 * Controla las transiciones de nodos de forma atómica usando
 * DB::transaction + lockForUpdate (ISO 27001 A.12.6.1).
 *
 * Estados de nodo en instancia: pendiente → en_curso → completado | saltado
 * (M19-27: una unión queda en_espera; M19-28: una devolución vuelve los pasos a pendiente)
 * Estados de instancia: en_curso → completada | detenida | cancelada
 */
class WorkflowExecutionService
{
    /** Ramas de un nodo de condición (etiqueta de la conexión). */
    public const DECISIONES = ['Sí', 'No'];

    /** Pasos a los que se puede devolver una instancia (los automáticos no se rehacen). */
    public const TIPOS_DEVOLVIBLES = ['tarea', 'notificacion', 'condicion'];

    /** Pasos automáticos (paralelos, uniones, fines) por movimiento: corta ciclos sin tareas. */
    private const MAX_SALTOS = 200;

    private int $saltos = 0;

    private ?int $ultimoFin = null;

    public function __construct(
        private readonly WorkflowAuditService $auditService,
        private readonly WorkflowNotificationService $notificationService,
        private readonly TareasDeNodoService $tareasDeNodo
    ) {}

    public function iniciarInstancia(int $workflowId, int $userId, ?string $origenTipo = null, ?int $origenId = null): WorkflowInstancia
    {
        return DB::transaction(function () use ($workflowId, $userId, $origenTipo, $origenId) {
            $workflow = Workflow::with('nodos')->findOrFail($workflowId);

            // M19-16: borradores, inactivos y archivados no se ejecutan
            if ($workflow->estado !== 'activo') {
                throw new StateTransitionException("Solo se pueden iniciar workflows activos (estado actual: {$workflow->estado})");
            }

            $nodoInicio = $workflow->nodos()->where('tipo', 'inicio')->first();
            if (! $nodoInicio) {
                throw new StateTransitionException('El workflow no tiene un nodo de inicio');
            }

            $radicadoId = $origenTipo ? $this->radicadoDeOrigen($origenTipo, (int) $origenId, $userId) : null;

            $fechaLimite = null;
            if ($workflow->tiempo_finalizacion_horas) {
                $fechaLimite = now()->addHours($workflow->tiempo_finalizacion_horas);
            }

            $instancia = WorkflowInstancia::create([
                'workflow_id' => $workflowId,
                'nodo_actual_id' => $nodoInicio->id,
                'estado' => 'en_curso',
                'usuario_ejecuta_id' => $userId,
                'fecha_inicio' => now(),
                'fecha_limite_estimada' => $fechaLimite,
            ] + ($origenTipo ? [
                'origen_tipo' => $origenTipo,
                'origen_id' => $origenId,
                'radicado_id' => $radicadoId,
            ] : []));

            foreach ($workflow->nodos as $nodo) {
                WorkflowNodoInstancia::create([
                    'instancia_id' => $instancia->id,
                    'nodo_id' => $nodo->id,
                    'estado' => $nodo->id === $nodoInicio->id ? 'completado' : 'pendiente',
                    'fecha_ejecucion' => $nodo->id === $nodoInicio->id ? now() : null,
                ]);
            }

            // El inicio queda completado: la instancia arranca en el nodo siguiente. Antes se quedaba
            // apuntando al inicio ya completado y ningún nodo podía ejecutarse.
            $this->avanzar($instancia, $this->determinarSiguienteNodo($instancia->id, $nodoInicio->id, []));

            $this->auditService->registrar(
                'instancia.iniciada',
                $workflowId,
                $instancia->id,
                array_filter([
                    'workflow' => $workflow->nombre,
                    'origen_tipo' => $origenTipo,
                    'origen_id' => $origenTipo ? $origenId : null,
                    'radicado_id' => $radicadoId,
                ], fn ($v) => $v !== null)
            );

            return $instancia->refresh()->load(['nodosInstancia.nodo', 'nodoActual', 'radicado:id,num_radicado']);
        });
    }

    /**
     * M19-30: el origen debe existir y ser visible para quien inicia el flujo (mismo ABAC que su
     * bandeja). Devuelve el radicado recibido al que se archivarán los documentos del flujo.
     */
    private function radicadoDeOrigen(string $origenTipo, int $origenId, int $userId): int
    {
        $user = User::findOrFail($userId);

        $radicadoId = match ($origenTipo) {
            'radicado_recibido' => VentanillaRadicaReci::accesiblePara($user, $origenId) ? $origenId : null,
            'pqrs' => VentanillaPqrsOptimizedView::conPermisoJerarquico($user)->whereKey($origenId)->value('ventanilla_radica_reci_id'),
            default => null,
        };

        if (! $radicadoId) {
            throw new StateTransitionException(
                $origenTipo === 'pqrs' ? 'La PQRS no existe o no tiene acceso a ella' : 'El radicado no existe o no tiene acceso a él'
            );
        }

        return (int) $radicadoId;
    }

    public function obtenerInstancia(int $instanciaId): WorkflowInstancia
    {
        return WorkflowInstancia::with([
            'workflow:id,nombre',
            'radicado:id,num_radicado',
            'nodoActual',
            'nodosInstancia.nodo',
        ])->findOrFail($instanciaId);
    }

    public function ejecutarNodo(int $instanciaId, int $nodoId, array $resultado = [], ?int $workflowId = null): WorkflowInstancia
    {
        return DB::transaction(function () use ($instanciaId, $nodoId, $resultado, $workflowId) {
            // M19-09: la instancia debe pertenecer al workflow de la ruta (si no, 404)
            $instancia = WorkflowInstancia::where('id', $instanciaId)
                ->when($workflowId !== null, fn ($q) => $q->where('workflow_id', $workflowId))
                ->lockForUpdate()
                ->firstOrFail();

            if ($instancia->estado !== 'en_curso') {
                throw new StateTransitionException(
                    "La instancia está {$instancia->estado}, no se puede ejecutar nodos"
                );
            }

            $nodoInstancia = WorkflowNodoInstancia::where('instancia_id', $instanciaId)
                ->where('nodo_id', $nodoId)
                ->firstOrFail();

            if ($nodoInstancia->estado === 'completado') {
                throw new StateTransitionException('Este nodo ya fue ejecutado');
            }

            // Solo se ejecutan nodos en curso (M19-27: con ramas paralelas puede haber varios);
            // evita saltar directo al nodo fin o ejecutar nodos futuros
            if ($nodoInstancia->estado !== 'en_curso') {
                throw new StateTransitionException('El nodo '.$nodoId.' no está en curso en la instancia');
            }

            // M19-19: el nodo no avanza con tareas abiertas (completarlas lo avanza solo)
            $abiertas = $this->tareasDeNodo->abiertas($instanciaId, $nodoId, true);
            if ($abiertas > 0) {
                throw new StateTransitionException("El nodo tiene {$abiertas} tarea(s) sin completar");
            }

            $siguienteNodo = $this->determinarSiguienteNodo($instanciaId, $nodoId, $resultado);

            $resultadoFinal = $resultado;
            $nodo = $nodoInstancia->nodo;
            if ($nodo && $nodo->tipo === 'condicion') {
                $resultadoFinal = array_merge($resultado, [
                    '_evaluacion' => [
                        'rama_tomada' => $resultado['decision'],
                        'decidido_por' => auth()->id(),
                        'evaluado_en' => now()->toIso8601String(),
                    ],
                ]);
            }

            $nodoInstancia->update([
                'estado' => 'completado',
                'fecha_ejecucion' => now(),
                'resultado_json' => $resultadoFinal,
            ]);

            $this->avanzar($instancia, $siguienteNodo);

            $this->auditService->registrar(
                'instancia.nodo_ejecutado',
                $instancia->workflow_id,
                $instancia->id,
                [
                    'nodo_id' => $nodoId,
                    'siguiente_nodo_id' => $siguienteNodo?->id,
                    'nuevo_estado_instancia' => $instancia->fresh()->estado,
                ]
            );

            $instanciaRefreshed = $instancia->fresh();
            if ($instanciaRefreshed->estado === 'completada') {
                $this->notificationService->notificarInstanciaCompletada($instanciaRefreshed);
            } elseif ($siguienteNodo) {
                $this->notificationService->notificarInstanciaAvanzada(
                    $instanciaRefreshed,
                    $siguienteNodo->titulo
                );
            }

            return $instanciaRefreshed->load(['nodosInstancia.nodo', 'nodoActual']);
        });
    }

    public function detenerInstancia(int $instanciaId, string $estado = 'detenida', ?int $workflowId = null): WorkflowInstancia
    {
        return DB::transaction(function () use ($instanciaId, $estado, $workflowId) {
            // M19-10: bloqueo de fila, igual que ejecutarNodo (sin carrera detener vs avanzar)
            $instancia = WorkflowInstancia::where('id', $instanciaId)
                ->when($workflowId !== null, fn ($q) => $q->where('workflow_id', $workflowId))
                ->lockForUpdate()
                ->firstOrFail();

            // Solo instancias en curso pueden detenerse/cancelarse
            if ($instancia->estado !== 'en_curso') {
                throw new StateTransitionException(
                    "La instancia está {$instancia->estado}, solo 'en_curso' puede detenerse"
                );
            }

            $estadoAnterior = $instancia->getOriginal('estado');
            $instancia->update(['estado' => $estado]);
            $this->tareasDeNodo->cancelarAbiertas($instancia);

            $this->auditService->registrar(
                'instancia.'.$estado,
                $instancia->workflow_id,
                $instancia->id,
                ['estado_anterior' => $estadoAnterior]
            );

            $this->notificationService->notificarInstanciaAvanzada(
                $instancia,
                "instancia {$estado}"
            );

            return $instancia->load(['nodosInstancia.nodo', 'nodoActual']);
        });
    }

    /**
     * M19-28: devolución/reproceso. Desde un paso en curso se vuelve a un paso anterior ya completado:
     * los pasos entre ambos vuelven a pendiente (con sus tareas abiertas canceladas) y el destino
     * vuelve a estar en curso con tareas nuevas. El motivo queda en la instancia y en la auditoría.
     */
    public function devolverNodo(int $instanciaId, int $origenId, int $destinoId, string $motivo, ?int $workflowId = null): WorkflowInstancia
    {
        return DB::transaction(function () use ($instanciaId, $origenId, $destinoId, $motivo, $workflowId) {
            $instancia = WorkflowInstancia::where('id', $instanciaId)
                ->when($workflowId !== null, fn ($q) => $q->where('workflow_id', $workflowId))
                ->lockForUpdate()
                ->firstOrFail();

            if ($instancia->estado !== 'en_curso') {
                throw new StateTransitionException("La instancia está {$instancia->estado}, no se puede devolver");
            }

            $nodos = WorkflowNodoInstancia::with('nodo')->where('instancia_id', $instancia->id)->get()->keyBy('nodo_id');
            $origen = $nodos->get($origenId);
            $destino = $nodos->get($destinoId);

            if (! $origen || $origen->estado !== 'en_curso') {
                throw new StateTransitionException('Solo se puede devolver desde un paso en curso');
            }
            if (! $destino || $destino->estado !== 'completado' || ! in_array($destino->nodo?->tipo, self::TIPOS_DEVOLVIBLES, true)) {
                throw new StateTransitionException('Solo se puede devolver a un paso de tarea, notificación o condición ya completado');
            }

            $adyacencia = WorkflowConexion::where('workflow_id', $instancia->workflow_id)->get(['nodo_origen_id', 'nodo_destino_id'])
                ->groupBy('nodo_origen_id')->map(fn ($c) => $c->pluck('nodo_destino_id')->all())->all();
            $alcanzables = $this->alcanzables($destinoId, $adyacencia);

            if (! isset($alcanzables[$origenId])) {
                throw new StateTransitionException('El paso de destino no es anterior al paso actual');
            }
            // Con ramas paralelas, devolver antes del paralelo también rehace las otras ramas: no se
            // permite mientras alguna siga en curso (hay que terminarla o devolverla por separado)
            $otraRama = $nodos->first(fn ($n) => $n->estado === 'en_curso' && $n->nodo_id !== $origenId && isset($alcanzables[$n->nodo_id]));
            if ($otraRama) {
                throw new StateTransitionException(
                    "No se puede devolver a ese paso mientras \"{$otraRama->nodo?->titulo}\" sigue en curso en otra rama"
                );
            }

            $rehacer = collect(array_keys($alcanzables))->push($destinoId)->unique()->all();
            $this->tareasDeNodo->cancelarAbiertasDeNodos($instancia, $rehacer);
            WorkflowNodoInstancia::where('instancia_id', $instancia->id)
                ->whereIn('nodo_id', $rehacer)
                ->update(['estado' => 'pendiente', 'fecha_ejecucion' => null, 'updated_at' => now()]);

            $contexto = $instancia->datos_contexto ?? [];
            $contexto['devoluciones'][] = [
                'nodo_origen_id' => $origenId,
                'nodo_origen' => $origen->nodo?->titulo,
                'nodo_destino_id' => $destinoId,
                'nodo_destino' => $destino->nodo->titulo,
                'motivo' => $motivo,
                'user_id' => auth()->id(),
                'fecha' => now()->toIso8601String(),
            ];
            $instancia->update(['datos_contexto' => $contexto]);

            $this->avanzar($instancia, $destino->nodo);

            $this->auditService->registrar(
                'instancia.devuelta',
                $instancia->workflow_id,
                $instancia->id,
                ['nodo_origen_id' => $origenId, 'nodo_destino_id' => $destinoId, 'motivo' => $motivo]
            );

            $instanciaRefreshed = $instancia->fresh();
            $this->notificationService->notificarInstanciaAvanzada(
                $instanciaRefreshed,
                "devuelta a {$destino->nodo->titulo}"
            );

            return $instanciaRefreshed->load(['nodosInstancia.nodo', 'nodoActual']);
        });
    }

    /**
     * M19-27: mueve la instancia al nodo dado y resuelve lo automático (paralelos y uniones).
     * La instancia termina cuando llega a un fin y no le quedan ramas abiertas.
     */
    private function avanzar(WorkflowInstancia $instancia, ?WorkflowNodo $nodo): void
    {
        $this->saltos = 0;
        $this->ultimoFin = null;

        $this->entrarEnNodo($instancia, $nodo);
        $this->liberarUniones($instancia);

        $abiertos = WorkflowNodoInstancia::where('instancia_id', $instancia->id)
            ->whereIn('estado', ['en_curso', 'en_espera'])
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->get(['nodo_id', 'estado']);

        if ($abiertos->isEmpty()) {
            $instancia->update([
                'nodo_actual_id' => $this->ultimoFin ?? $instancia->nodo_actual_id,
                'estado' => 'completada',
                'fecha_fin' => now(),
            ]);
        } else {
            $actual = $abiertos->firstWhere('estado', 'en_curso') ?? $abiertos->first();
            $instancia->update(['nodo_actual_id' => $actual->nodo_id]);
        }
    }

    /**
     * Un fin consume la rama; un paralelo abre todas sus salidas; una unión queda en espera;
     * cualquier otro nodo queda en curso y genera sus tareas. Sin nodo es un error de diseño.
     */
    private function entrarEnNodo(WorkflowInstancia $instancia, ?WorkflowNodo $nodo): void
    {
        if (! $nodo) {
            // M19-20: solo un nodo fin termina la instancia; un callejón es un error de diseño
            // (la transacción se revierte y la instancia sigue donde estaba)
            throw new StateTransitionException(
                'El flujo no tiene salida desde este nodo; corrija el diseño (debe llegar a un nodo fin)'
            );
        }
        if (++$this->saltos > self::MAX_SALTOS) {
            throw new StateTransitionException('El flujo entra en un ciclo sin pasos que ejecutar; corrija el diseño');
        }

        $nodoInstancia = WorkflowNodoInstancia::where('instancia_id', $instancia->id)
            ->where('nodo_id', $nodo->id)
            ->first();

        switch ($nodo->tipo) {
            case 'fin':
                $nodoInstancia?->update(['estado' => 'completado', 'fecha_ejecucion' => now()]);
                $this->ultimoFin = $nodo->id;
                break;
            case 'paralelo':
                $nodoInstancia?->update(['estado' => 'completado', 'fecha_ejecucion' => now()]);
                $destinos = WorkflowConexion::with('nodoDestino')->where('nodo_origen_id', $nodo->id)->orderBy('id')->get()
                    ->pluck('nodoDestino')->filter()->unique('id');
                foreach ($destinos as $destino) {
                    $this->entrarEnNodo($instancia, $destino);
                }
                break;
            case 'union':
                $nodoInstancia?->update(['estado' => 'en_espera']);
                break;
            default:
                // Dos ramas que llegan al mismo paso sin unión no duplican sus tareas
                if ($nodoInstancia?->estado === 'en_curso') {
                    break;
                }
                $nodoInstancia?->update(['estado' => 'en_curso']);

                // M19-17: las plantillas del nodo se convierten en Tareas en "Mis tareas" de su responsable
                $this->tareasDeNodo->crearDesdePlantillas($instancia, $nodo);
        }
    }

    /**
     * Una unión en espera sigue cuando ninguna otra rama abierta puede llegar a ella: así espera a
     * todas las ramas de su paralelo, pero no a las que una condición descartó o que acabaron en un fin.
     */
    private function liberarUniones(WorkflowInstancia $instancia): void
    {
        $adyacencia = WorkflowConexion::where('workflow_id', $instancia->workflow_id)->get(['nodo_origen_id', 'nodo_destino_id'])
            ->groupBy('nodo_origen_id')->map(fn ($c) => $c->pluck('nodo_destino_id')->all())->all();

        while (true) {
            $abiertos = WorkflowNodoInstancia::where('instancia_id', $instancia->id)
                ->whereIn('estado', ['en_curso', 'en_espera'])->get(['id', 'nodo_id', 'estado']);
            $esperando = $abiertos->where('estado', 'en_espera');

            $lista = $esperando->first(fn ($union) => $abiertos->every(
                fn ($otro) => $otro->id === $union->id || ! $this->alcanza($otro->nodo_id, $union->nodo_id, $adyacencia)
            ));
            // Uniones que se esperan entre sí (ciclo) y nada más abierto: se libera la primera
            if (! $lista && $esperando->isNotEmpty() && $esperando->count() === $abiertos->count()) {
                $lista = $esperando->first();
            }
            if (! $lista) {
                return;
            }

            $lista->update(['estado' => 'completado', 'fecha_ejecucion' => now()]);
            $this->entrarEnNodo($instancia, $this->determinarSiguienteNodo($instancia->id, $lista->nodo_id, []));
        }
    }

    /** Nodos a los que se llega desde $desde siguiendo las conexiones (sin incluirlo, salvo ciclo). */
    private function alcanzables(int $desde, array $adyacencia): array
    {
        $visitados = [];
        $pendientes = [$desde];
        while ($pendientes) {
            foreach ($adyacencia[array_pop($pendientes)] ?? [] as $siguiente) {
                if (! isset($visitados[$siguiente])) {
                    $visitados[$siguiente] = true;
                    $pendientes[] = $siguiente;
                }
            }
        }

        return $visitados;
    }

    private function alcanza(int $desde, int $hasta, array $adyacencia): bool
    {
        $visitados = [$desde => true];
        $pendientes = [$desde];
        while ($pendientes) {
            foreach ($adyacencia[array_pop($pendientes)] ?? [] as $siguiente) {
                if ($siguiente === $hasta) {
                    return true;
                }
                if (! isset($visitados[$siguiente])) {
                    $visitados[$siguiente] = true;
                    $pendientes[] = $siguiente;
                }
            }
        }

        return false;
    }

    private function determinarSiguienteNodo(int $instanciaId, int $nodoActualId, array $resultado): ?WorkflowNodo
    {
        $conexionesSalientes = WorkflowConexion::where('nodo_origen_id', $nodoActualId)->get();

        if ($conexionesSalientes->isEmpty()) {
            return null;
        }

        $nodoOrigen = WorkflowNodo::find($nodoActualId);

        if ($nodoOrigen && $nodoOrigen->tipo === 'condicion') {
            return $this->ramaDecidida($conexionesSalientes, $resultado['decision'] ?? null)->nodoDestino;
        }

        return $conexionesSalientes->first()?->nodoDestino;
    }

    /**
     * M19-27: la rama de una condición la decide quien la ejecuta (Sí/No). Antes se evaluaba una
     * condicion_json que el builder nunca escribía y el flujo seguía siempre por "Sí".
     * Las condiciones guardadas sin etiquetas toman la primera conexión como Sí y la última como No.
     */
    private function ramaDecidida(Collection $conexiones, mixed $decision): WorkflowConexion
    {
        if (! in_array($decision, self::DECISIONES, true)) {
            throw new StateTransitionException('Elija la rama de la condición: Sí o No');
        }

        $rama = $conexiones->firstWhere('etiqueta', $decision);
        if (! $rama && $conexiones->whereIn('etiqueta', self::DECISIONES)->isEmpty() && $conexiones->count() === 2) {
            $rama = $decision === 'Sí' ? $conexiones->first() : $conexiones->last();
        }

        return $rama ?? throw new StateTransitionException(
            "La condición no tiene rama \"{$decision}\"; corrija el diseño"
        );
    }
}
