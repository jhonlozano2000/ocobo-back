<?php

namespace App\Services\Workflows;

use App\Exceptions\Workflows\StateTransitionException;
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
 * Estados de instancia: en_curso → completada | detenida | cancelada
 */
class WorkflowExecutionService
{
    /** Ramas de un nodo de condición (etiqueta de la conexión). */
    public const DECISIONES = ['Sí', 'No'];

    public function __construct(
        private readonly WorkflowAuditService $auditService,
        private readonly WorkflowNotificationService $notificationService,
        private readonly TareasDeNodoService $tareasDeNodo
    ) {}

    public function iniciarInstancia(int $workflowId, int $userId): WorkflowInstancia
    {
        return DB::transaction(function () use ($workflowId, $userId) {
            $workflow = Workflow::with('nodos')->findOrFail($workflowId);

            // M19-16: borradores, inactivos y archivados no se ejecutan
            if ($workflow->estado !== 'activo') {
                throw new StateTransitionException("Solo se pueden iniciar workflows activos (estado actual: {$workflow->estado})");
            }

            $nodoInicio = $workflow->nodos()->where('tipo', 'inicio')->first();
            if (! $nodoInicio) {
                throw new StateTransitionException('El workflow no tiene un nodo de inicio');
            }

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
            ]);

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
            $this->entrarEnNodo($instancia, $this->determinarSiguienteNodo($instancia->id, $nodoInicio->id, []));

            $this->auditService->registrar(
                'instancia.iniciada',
                $workflowId,
                $instancia->id,
                ['workflow' => $workflow->nombre]
            );

            return $instancia->load(['nodosInstancia.nodo', 'nodoActual']);
        });
    }

    public function obtenerInstancia(int $instanciaId): WorkflowInstancia
    {
        return WorkflowInstancia::with([
            'workflow:id,nombre',
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

            // Solo se puede ejecutar el nodo ACTUAL de la instancia
            // (evita saltar directo al nodo fin o ejecutar nodos futuros)
            if ($instancia->nodo_actual_id !== $nodoId) {
                throw new StateTransitionException(
                    'El nodo '.$nodoId.' no es el nodo actual de la instancia'
                );
            }

            if ($nodoInstancia->estado === 'completado') {
                throw new StateTransitionException('Este nodo ya fue ejecutado');
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

            $this->entrarEnNodo($instancia, $siguienteNodo);

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
     * Lleva la instancia al nodo dado: un fin la completa (sin siguiente es error de diseño);
     * cualquier otro nodo pasa a ser el actual, queda en curso y genera sus tareas.
     */
    private function entrarEnNodo(WorkflowInstancia $instancia, ?WorkflowNodo $siguienteNodo): void
    {
        if ($siguienteNodo && $siguienteNodo->tipo === 'fin') {
            $instancia->update([
                'nodo_actual_id' => $siguienteNodo->id,
                'estado' => 'completada',
                'fecha_fin' => now(),
            ]);

            WorkflowNodoInstancia::where('instancia_id', $instancia->id)
                ->where('nodo_id', $siguienteNodo->id)
                ->update(['estado' => 'completado', 'fecha_ejecucion' => now()]);
        } elseif ($siguienteNodo) {
            $instancia->update(['nodo_actual_id' => $siguienteNodo->id]);

            WorkflowNodoInstancia::where('instancia_id', $instancia->id)
                ->where('nodo_id', $siguienteNodo->id)
                ->update(['estado' => 'en_curso']);

            // M19-17: las plantillas del nodo se convierten en Tareas en "Mis tareas" de su responsable
            $this->tareasDeNodo->crearDesdePlantillas($instancia, $siguienteNodo);
        } else {
            // M19-20: solo un nodo fin termina la instancia; un callejón es un error de diseño
            // (la transacción se revierte y la instancia sigue donde estaba)
            throw new StateTransitionException(
                'El flujo no tiene salida desde este nodo; corrija el diseño (debe llegar a un nodo fin)'
            );
        }
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
