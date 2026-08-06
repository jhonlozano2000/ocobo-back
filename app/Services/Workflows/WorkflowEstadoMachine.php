<?php

declare(strict_types=1);

namespace App\Services\Workflows;

/**
 * Máquina de estados compartida para tareas de workflow.
 *
 * Estados: pendiente → en_curso → completada (terminal)
 *                            → vencida → en_curso (reactivar)
 *                                     → cancelada (terminal)
 *                   pendiente → cancelada (terminal)
 *
 * ISO 27001 A.9.4: Control de estados de sesión y tareas.
 */
class WorkflowEstadoMachine
{
    private const TRANSICIONES = [
        'pendiente' => ['en_curso', 'cancelada'],
        'en_curso' => ['completada', 'vencida', 'cancelada'],
        'en_progreso' => ['completada', 'vencida', 'cancelada'],
        'completada' => [],
        'vencida' => ['en_curso', 'cancelada'],
        'cancelada' => [],
    ];

    public static function puedeTransitar(string $desde, string $hasta): bool
    {
        $desde = self::normalizar($desde);

        return in_array($hasta, self::transicionesPermitidas($desde), true);
    }

    public static function transicionesPermitidas(string $estadoActual): array
    {
        $estadoActual = self::normalizar($estadoActual);

        return self::TRANSICIONES[$estadoActual] ?? [];
    }

    public static function esTerminal(string $estado): bool
    {
        $estado = self::normalizar($estado);

        return empty(self::TRANSICIONES[$estado]);
    }

    public static function normalizar(string $estado): string
    {
        return $estado === 'en_progreso' ? 'en_curso' : $estado;
    }

    public static function necesitaVencimiento(string $estado, ?string $fechaLimite): bool
    {
        $estado = self::normalizar($estado);

        if (!in_array($estado, ['pendiente', 'en_curso'], true)) {
            return false;
        }

        if (!$fechaLimite) {
            return false;
        }

        return now()->greaterThan($fechaLimite);
    }
}
