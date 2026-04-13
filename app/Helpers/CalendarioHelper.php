<?php

namespace App\Helpers;

use App\Models\Configuracion\ConfigCalendarioFestivo;
use App\Models\Configuracion\ConfigVarias;
use App\Services\Configuracion\BusinessDaysService;
use Carbon\Carbon;

class CalendarioHelper
{
    private static ?BusinessDaysService $service = null;

    protected static function getService(): BusinessDaysService
    {
        if (self::$service === null) {
            self::$service = new BusinessDaysService();
        }
        return self::$service;
    }

    /**
     * Calcula una fecha de vencimiento sumando días hábiles.
     * Descuenta sábados, domingos y festivos de la base de datos.
     * Respeta la configuración de "considerar_dias_habiles".
     *
     * @param Carbon|string $fechaInicio
     * @param int $diasHabiles
     * @return Carbon
     */
    public static function calcularVencimiento($fechaInicio, int $diasHabiles): Carbon
    {
        $fecha = Carbon::parse($fechaInicio);

        if (!ConfigVarias::getConsiderarDiasHabiles(true)) {
            return $fecha->addDays($diasHabiles);
        }

        return self::getService()->calcularVencimiento($fecha, $diasHabiles);
    }

    /**
     * Calcula la diferencia de días hábiles entre hoy y una fecha futura.
     * Útil para el semáforo de PQRS.
     *
     * @param Carbon|string $fechaVencimiento
     * @return int
     */
    public static function diasHabilesRestantes($fechaVencimiento): int
    {
        $vence = Carbon::parse($fechaVencimiento);

        if (!ConfigVarias::getConsiderarDiasHabiles(true)) {
            return (int) Carbon::today()->diffInDays($vence, false);
        }

        return self::getService()->diasHabilesRestantes($vence);
    }

    /**
     * Verifica si una fecha es día hábil.
     *
     * @param Carbon|string $fecha
     * @return bool
     */
    public static function esDiaHabil($fecha): bool
    {
        $fechaCarbon = Carbon::parse($fecha);

        if (!ConfigVarias::getConsiderarDiasHabiles(true)) {
            return !$fechaCarbon->isWeekend();
        }

        return self::getService()->esDiaHabil($fechaCarbon);
    }

    /**
     * Verifica si una fecha es festiva.
     *
     * @param Carbon|string $fecha
     * @return bool
     */
    public static function esFestivo($fecha): bool
    {
        return self::getService()->esFestivo(Carbon::parse($fecha));
    }

    /**
     * Obtiene el próximo día hábil a partir de una fecha.
     *
     * @param Carbon|string $fecha
     * @return Carbon
     */
    public static function getProximoDiaHabil($fecha): Carbon
    {
        return self::getService()->getProximoDiaHabil(Carbon::parse($fecha));
    }

    /**
     * Obtiene la fecha de vencimiento usando los días por defecto de configuración.
     *
     * @param Carbon|string $fechaInicio
     * @return Carbon
     */
    public static function calcularVencimientoPorDefecto($fechaInicio): Carbon
    {
        $dias = ConfigVarias::getDiasVencimientoPredeterminado(5);
        return self::calcularVencimiento($fechaInicio, $dias);
    }

    /**
     * Calcula el vencimiento según la clasificación documental (TRD).
     *
     * @param Carbon|string $fechaInicio
     * @param int $clasificacionTrdId ID del elemento en clasificacion_documental_trd
     * @return Carbon
     */
    public static function calcularVencimientoPorClasificacion($fechaInicio, int $clasificacionTrdId): Carbon
    {
        $clasificacion = \App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD::find($clasificacionTrdId);

        if (!$clasificacion) {
            return self::calcularVencimientoPorDefecto($fechaInicio);
        }

        $dias = $clasificacion->getDiasVencimiento();
        return self::calcularVencimiento($fechaInicio, $dias);
    }
}
