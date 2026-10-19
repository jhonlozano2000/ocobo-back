<?php

namespace App\Services\Workflows;

use App\Helpers\CalendarioHelper;
use Carbon\Carbon;

/**
 * M19-30: vencimiento de un plazo del flujo medido en horas o en días hábiles. Los días hábiles
 * descuentan fines de semana y festivos y vencen al final del último día, como los términos legales.
 */
final class PlazoWorkflow
{
    public const UNIDADES = ['horas', 'dias_habiles'];

    public static function vencimiento(?int $valor, ?string $unidad, ?Carbon $desde = null): ?Carbon
    {
        if (! $valor) {
            return null;
        }
        $desde = ($desde ?? now())->copy();

        return $unidad === 'dias_habiles'
            ? CalendarioHelper::calcularVencimiento($desde, $valor)->endOfDay()
            : $desde->addHours($valor);
    }
}
