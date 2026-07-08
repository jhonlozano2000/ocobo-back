<?php

namespace App\Services\Workflows;

use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;
use App\Models\Workflows\WorkflowNodoInstancia;
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
    public function __construct(
        private readonly WorkflowAuditService $auditService
    ) {}

    public function iniciarInstancia(int $workflowId, int $userId): WorkflowInstancia
    {
        return DB::transaction(function () use ($workflowId, $userId) {
            $workflow = Workflow::with('nodos')->findOrFail($workflowId);

            $nodoInicio = $workflow->nodos()->where('tipo', 'inicio')->first();
            if (!$nodoInicio) {
                throw new \RuntimeException('El workflow no tiene un nodo de inicio');
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

    public function ejecutarNodo(int $instanciaId, int $nodoId, array $resultado = []): WorkflowInstancia
    {
        return DB::transaction(function () use ($instanciaId, $nodoId, $resultado) {
            $instancia = WorkflowInstancia::where('id', $instanciaId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($instancia->estado !== 'en_curso') {
                throw new \RuntimeException(
                    "La instancia está {$instancia->estado}, no se puede ejecutar nodos"
                );
            }

            $nodoInstancia = WorkflowNodoInstancia::where('instancia_id', $instanciaId)
                ->where('nodo_id', $nodoId)
                ->firstOrFail();

            $nodoInstancia->update([
                'estado' => 'completado',
                'fecha_ejecucion' => now(),
                'resultado_json' => $resultado,
            ]);

            $siguienteNodo = $this->determinarSiguienteNodo($instanciaId, $nodoId, $resultado);

            if ($siguienteNodo && $siguienteNodo->tipo === 'fin') {
                $instancia->update([
                    'nodo_actual_id' => $siguienteNodo->id,
                    'estado' => 'completada',
                    'fecha_fin' => now(),
                ]);

                WorkflowNodoInstancia::where('instancia_id', $instanciaId)
                    ->where('nodo_id', $siguienteNodo->id)
                    ->update(['estado' => 'completado', 'fecha_ejecucion' => now()]);
            } elseif ($siguienteNodo) {
                $instancia->update(['nodo_actual_id' => $siguienteNodo->id]);

                WorkflowNodoInstancia::where('instancia_id', $instanciaId)
                    ->where('nodo_id', $siguienteNodo->id)
                    ->update(['estado' => 'en_curso']);
            } else {
                $instancia->update(['estado' => 'completada', 'fecha_fin' => now()]);
            }

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

            return $instancia->fresh()->load(['nodosInstancia.nodo', 'nodoActual']);
        });
    }

    public function detenerInstancia(int $instanciaId, string $estado = 'detenida'): WorkflowInstancia
    {
        return DB::transaction(function () use ($instanciaId, $estado) {
            $instancia = WorkflowInstancia::findOrFail($instanciaId);
            $instancia->update(['estado' => $estado]);

            $this->auditService->registrar(
                'instancia.' . $estado,
                $instancia->workflow_id,
                $instancia->id,
                ['estado_anterior' => $instancia->getOriginal('estado')]
            );

            return $instancia->load(['nodosInstancia.nodo', 'nodoActual']);
        });
    }

    private function determinarSiguienteNodo(int $instanciaId, int $nodoActualId, array $resultado): ?WorkflowNodo
    {
        $conexionesSalientes = \App\Models\Workflows\WorkflowConexion::where('nodo_origen_id', $nodoActualId)->get();

        if ($conexionesSalientes->isEmpty()) {
            return null;
        }

        $nodoOrigen = \App\Models\Workflows\WorkflowNodo::find($nodoActualId);

        if ($nodoOrigen && $nodoOrigen->tipo === 'condicion') {
            $ramaVerdadera = $conexionesSalientes->firstWhere('etiqueta', 'Sí');
            $ramaFalsa = $conexionesSalientes->firstWhere('etiqueta', 'No');

            $cumpleCondicion = $this->evaluarCondicion($conexionesSalientes->first()?->condicion_json, $resultado);

            $conexionElegida = $cumpleCondicion ? ($ramaVerdadera ?? $conexionesSalientes->first())
                : ($ramaFalsa ?? $conexionesSalientes->last());

            return $conexionElegida?->nodoDestino;
        }

        return $conexionesSalientes->first()?->nodoDestino;
    }

    private function evaluarCondicion(?array $condicion, array $contexto): bool
    {
        if (empty($condicion)) {
            return true;
        }

        $campo = $condicion['campo'] ?? null;
        $operador = $condicion['operador'] ?? '==';
        $valorEsperado = $condicion['valor'] ?? null;

        if (!$campo) {
            return true;
        }

        $valorReal = $contexto[$campo] ?? null;

        return match ($operador) {
            '==' => $valorReal == $valorEsperado,
            '!=' => $valorReal != $valorEsperado,
            '>' => $valorReal > $valorEsperado,
            '>=' => $valorReal >= $valorEsperado,
            '<' => $valorReal < $valorEsperado,
            '<=' => $valorReal <= $valorEsperado,
            'contains' => is_string($valorReal) && str_contains($valorReal, (string) $valorEsperado),
            'in' => in_array($valorReal, (array) $valorEsperado),
            default => $valorReal == $valorEsperado,
        };
    }
}
