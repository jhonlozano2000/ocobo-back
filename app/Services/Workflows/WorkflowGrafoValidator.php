<?php

namespace App\Services\Workflows;

use App\Models\Workflows\Workflow;
use Illuminate\Validation\ValidationException;

/**
 * M19-18: reglas del grafo de un workflow.
 *
 * - Consistencia (todo guardado): un solo inicio, sin conexiones a sí mismo, nada entra al inicio
 *   ni sale de un fin. Un borrador puede estar incompleto mientras se diseña.
 * - Completitud (workflow activo o al activarlo): exactamente un inicio, al menos un fin, todos los
 *   nodos alcanzables desde el inicio y con camino a algún fin (los ciclos sin salida no pasan).
 *
 * Los nodos se reciben como [clave => tipo] y las conexiones como [[origen, destino], ...].
 */
class WorkflowGrafoValidator
{
    /**
     * @param  array<int|string, string>  $nodos
     * @param  array<int, array{0: int|string, 1: int|string}>  $conexiones
     */
    public function validar(array $nodos, array $conexiones, bool $completo): void
    {
        $errores = $this->errores($nodos, $conexiones, $completo);

        if ($errores) {
            throw ValidationException::withMessages(['grafo' => $errores]);
        }
    }

    public function validarWorkflow(Workflow $workflow): void
    {
        $this->validar(
            $workflow->nodos()->pluck('tipo', 'id')->all(),
            $workflow->conexiones()->get(['nodo_origen_id', 'nodo_destino_id'])
                ->map(fn ($c) => [$c->nodo_origen_id, $c->nodo_destino_id])->all(),
            true
        );
    }

    /**
     * @return list<string>
     */
    public function errores(array $nodos, array $conexiones, bool $completo): array
    {
        $errores = [];
        $inicios = array_keys($nodos, 'inicio', true);
        $fines = array_keys($nodos, 'fin', true);

        if (count($inicios) > 1) {
            $errores[] = 'El flujo solo puede tener un nodo de inicio';
        }

        $salientes = [];
        $entrantes = [];
        foreach ($conexiones as [$origen, $destino]) {
            if ($origen === $destino) {
                $errores[] = 'Un nodo no puede conectarse consigo mismo';

                continue;
            }
            if (($nodos[$destino] ?? null) === 'inicio') {
                $errores[] = 'El nodo de inicio no puede tener conexiones de entrada';
            }
            if (($nodos[$origen] ?? null) === 'fin') {
                $errores[] = 'Un nodo de fin no puede tener conexiones de salida';
            }
            $salientes[$origen][] = $destino;
            $entrantes[$destino][] = $origen;
        }

        if ($completo) {
            if (count($inicios) === 0) {
                $errores[] = 'El flujo necesita un nodo de inicio';
            }
            if (count($fines) === 0) {
                $errores[] = 'El flujo necesita al menos un nodo de fin';
            }
            if (count($inicios) === 1 && count($fines) > 0) {
                $alcanzables = $this->recorrer($inicios, $salientes);
                $conSalida = $this->recorrer($fines, $entrantes);
                if (count(array_diff_key($nodos, $alcanzables)) > 0) {
                    $errores[] = 'Hay nodos que no se alcanzan desde el inicio';
                }
                if (count(array_diff_key($nodos, $conSalida)) > 0) {
                    $errores[] = 'Hay nodos sin camino hacia un nodo de fin';
                }
            }
        }

        return array_values(array_unique($errores));
    }

    /**
     * @return array<int|string, true>
     */
    private function recorrer(array $desde, array $adyacencia): array
    {
        $visitados = array_fill_keys($desde, true);
        $pendientes = $desde;
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
}
