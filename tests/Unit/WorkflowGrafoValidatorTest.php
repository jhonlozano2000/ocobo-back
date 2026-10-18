<?php

namespace Tests\Unit;

use App\Services\Workflows\WorkflowGrafoValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M19-18: el canvas se guardaba sin validar el grafo (inicios repetidos, nodos inalcanzables, ciclos sin salida).
 */
class WorkflowGrafoValidatorTest extends TestCase
{
    private const LINEAL = [['a' => 'inicio', 'b' => 'tarea', 'c' => 'fin'], [['a', 'b'], ['b', 'c']]];

    public static function grafos(): array
    {
        return [
            'lineal' => [...self::LINEAL, true, []],
            'condición con retorno y salida' => [
                ['a' => 'inicio', 'b' => 'tarea', 'c' => 'condicion', 'd' => 'fin'],
                [['a', 'b'], ['b', 'c'], ['c', 'b', 'No'], ['c', 'd', 'Sí']], true, [],
            ],
            'condición sin ramas Sí/No no se activa' => [
                ['a' => 'inicio', 'c' => 'condicion', 'd' => 'fin', 'e' => 'fin'],
                [['a', 'c'], ['c', 'd'], ['c', 'e', 'Sí']], true,
                ['Cada condición necesita dos salidas: una "Sí" y otra "No"'],
            ],
            'condición con tres salidas no se activa' => [
                ['a' => 'inicio', 'c' => 'condicion', 'd' => 'fin'],
                [['a', 'c'], ['c', 'd', 'Sí'], ['c', 'd', 'No'], ['c', 'd', 'Sí']], true,
                ['Cada condición necesita dos salidas: una "Sí" y otra "No"'],
            ],
            'paralelo con unión' => [
                ['a' => 'inicio', 'p' => 'paralelo', 'x' => 'tarea', 'y' => 'tarea', 'u' => 'union', 'f' => 'fin'],
                [['a', 'p'], ['p', 'x'], ['p', 'y'], ['x', 'u'], ['y', 'u'], ['u', 'f']], true, [],
            ],
            'paralelo de una rama y unión de una entrada' => [
                ['a' => 'inicio', 'p' => 'paralelo', 'x' => 'tarea', 'u' => 'union', 'f' => 'fin', 'g' => 'fin'],
                [['a', 'p'], ['p', 'x'], ['x', 'u'], ['u', 'f'], ['u', 'g']], true,
                ['Cada nodo paralelo necesita al menos dos salidas', 'Cada unión necesita al menos dos entradas y una sola salida'],
            ],
            'borrador con condición a medias se acepta' => [['a' => 'inicio', 'c' => 'condicion'], [['a', 'c']], false, []],
            'borrador a medias se acepta' => [['a' => 'inicio', 'b' => 'tarea'], [['a', 'b']], false, []],
            'borrador vacío se acepta' => [[], [], false, []],
            'dos inicios' => [
                ['a' => 'inicio', 'b' => 'inicio', 'c' => 'fin'], [['a', 'c'], ['b', 'c']], false,
                ['El flujo solo puede tener un nodo de inicio'],
            ],
            'autoconexión' => [['a' => 'inicio', 'b' => 'tarea'], [['b', 'b']], false, ['Un nodo no puede conectarse consigo mismo']],
            'entrada al inicio' => [['a' => 'inicio', 'b' => 'tarea'], [['b', 'a']], false, ['El nodo de inicio no puede tener conexiones de entrada']],
            'salida de un fin' => [['a' => 'fin', 'b' => 'tarea'], [['a', 'b']], false, ['Un nodo de fin no puede tener conexiones de salida']],
            'vacío no se activa' => [[], [], true, ['El flujo necesita un nodo de inicio', 'El flujo necesita al menos un nodo de fin']],
            'nodo suelto' => [
                ['a' => 'inicio', 'b' => 'tarea', 'c' => 'fin', 'x' => 'tarea'], [['a', 'b'], ['b', 'c']], true,
                ['Hay nodos que no se alcanzan desde el inicio', 'Hay nodos sin camino hacia un nodo de fin'],
            ],
            'ciclo sin salida' => [
                ['a' => 'inicio', 'b' => 'tarea', 'c' => 'tarea', 'd' => 'fin'], [['a', 'b'], ['b', 'c'], ['c', 'b'], ['a', 'd']], true,
                ['Hay nodos sin camino hacia un nodo de fin'],
            ],
        ];
    }

    #[DataProvider('grafos')]
    public function test_reglas_del_grafo(array $nodos, array $conexiones, bool $completo, array $esperados): void
    {
        $this->assertSame($esperados, (new WorkflowGrafoValidator)->errores($nodos, $conexiones, $completo));
    }

    public function test_acepta_ids_numericos(): void
    {
        $this->assertSame([], (new WorkflowGrafoValidator)->errores([10 => 'inicio', 11 => 'fin'], [[10, 11]], true));
    }
}
