<?php

namespace App\Helpers;

use App\Models\Configuracion\ConfigCalendarioFestivo;
use Carbon\Carbon;

class CalendarioHelper
{
    /**
     * Calcula una fecha de vencimiento sumando días hábiles.
     * Descuenta sábados, domingos y festivos de la base de datos.
     *
     * @param Carbon|string $fechaInicio
     * @param int $diasHabiles
     * @return Carbon
     */
    public static function calcularVencimiento($fechaInicio, int $diasHabiles): Carbon
    {
        $fecha = Carbon::parse($fechaInicio)->clone();
        $cont = 0;

        // Obtener festivos en un rango amplio para optimizar (ej: un año desde el inicio)
        $festivos = ConfigCalendarioFestivo::where('fecha', '>=', $fecha->format('Y-m-d'))
            ->where('fecha', '<=', $fecha->clone()->addYear()->format('Y-m-d'))
            ->pluck('fecha')
            ->map(fn($f) => $f->format('Y-m-d'))
            ->toArray();

        while ($cont < $diasHabiles) {
            $fecha->addDay();

            // Verificar si el día es fin de semana o festivo
            $esFinDeSemana = $fecha->isWeekend();
            $esFestivo = in_array($fecha->format('Y-m-d'), $festivos);

            if (!$esFinDeSemana && !$esFestivo) {
                $cont++;
            }
        }

        return $fecha;
    }

    /**
     * Calcula la diferencia de días hábiles entre hoy y una fecha futura.
     * Útil para el semáforo de PQRS.
     */
    public static function diasHabilesRestantes($fechaVencimiento): int
    {
        $hoy = Carbon::today();
        $vence = Carbon::parse($fechaVencimiento);
        
        if ($hoy->gt($vence)) return 0;

        $dias = 0;
        $temp = $hoy->clone();

        $festivos = ConfigCalendarioFestivo::where('fecha', '>=', $temp->format('Y-m-d'))
            ->where('fecha', '<=', $vence->format('Y-m-d'))
            ->pluck('fecha')
            ->map(fn($f) => $f->format('Y-m-d'))
            ->toArray();

        while ($temp->lt($vence)) {
            $temp->addDay();
            if (!$temp->isWeekend() && !in_array($temp->format('Y-m-d'), $festivos)) {
                $dias++;
            }
        }

        return $dias;
    }
}
