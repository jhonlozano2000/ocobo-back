<?php

namespace App\Helpers;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\ConfigVarias;
use App\Services\Configuracion\BusinessDaysService;
use Carbon\Carbon;

class CalendarioHelper
{
    private static ?BusinessDaysService $service = null;

    protected static function getService(): BusinessDaysService
    {
        if (self::$service === null) {
            self::$service = new BusinessDaysService;
        }

        return self::$service;
    }

    /**
     * Fecha de hoy en la zona horaria de negocio (Colombia), como Carbon a medianoche
     * en la zona de la app para poder compararla con columnas `date` (M13-01).
     */
    public static function hoy(): Carbon
    {
        return Carbon::parse(Carbon::now(config('app.timezone_local'))->toDateString());
    }

    /**
     * Fecha calendario en Colombia de un instante guardado en UTC (p. ej. now() o un
     * `datetime`). Un string 'Y-m-d' ya es fecha calendario y se respeta tal cual.
     *
     * @param  Carbon|string|null  $instante
     */
    public static function fechaLocal($instante = null): Carbon
    {
        if ($instante === null) {
            return self::hoy();
        }

        if (is_string($instante) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $instante)) {
            return Carbon::parse($instante);
        }

        return Carbon::parse(
            Carbon::parse($instante)->setTimezone(config('app.timezone_local'))->toDateString()
        );
    }

    /**
     * Días calendario desde hoy (Colombia) hasta una fecha de vencimiento; negativo si ya pasó.
     *
     * @param  Carbon|string  $fecha
     */
    public static function diasHasta($fecha): int
    {
        return (int) self::hoy()->diffInDays(Carbon::parse($fecha)->startOfDay(), false);
    }

    /**
     * Una fecha de vencimiento está vencida desde el día siguiente (Colombia); el día
     * del vencimiento todavía está en término.
     *
     * @param  Carbon|string  $fecha
     */
    public static function vencida($fecha): bool
    {
        return Carbon::parse($fecha)->startOfDay()->lessThan(self::hoy());
    }

    /**
     * Calcula una fecha de vencimiento sumando días hábiles.
     * Descuenta sábados, domingos y festivos de la base de datos.
     * Respeta la configuración de "considerar_dias_habiles".
     *
     * @param  Carbon|string  $fechaInicio
     */
    public static function calcularVencimiento($fechaInicio, int $diasHabiles): Carbon
    {
        $fecha = Carbon::parse($fechaInicio);

        if (! ConfigVarias::getConsiderarDiasHabiles(true)) {
            return $fecha->addDays($diasHabiles);
        }

        return self::getService()->calcularVencimiento($fecha, $diasHabiles);
    }

    /**
     * Calcula la diferencia de días hábiles entre hoy y una fecha futura.
     * Útil para el semáforo de PQRS.
     *
     * @param  Carbon|string  $fechaVencimiento
     */
    public static function diasHabilesRestantes($fechaVencimiento): int
    {
        $vence = Carbon::parse($fechaVencimiento);

        if (! ConfigVarias::getConsiderarDiasHabiles(true)) {
            return (int) self::hoy()->diffInDays($vence->copy()->startOfDay(), false);
        }

        return self::getService()->diasHabilesRestantes($vence);
    }

    /**
     * Verifica si una fecha es día hábil.
     *
     * @param  Carbon|string  $fecha
     */
    public static function esDiaHabil($fecha): bool
    {
        $fechaCarbon = Carbon::parse($fecha);

        if (! ConfigVarias::getConsiderarDiasHabiles(true)) {
            return ! $fechaCarbon->isWeekend();
        }

        return self::getService()->esDiaHabil($fechaCarbon);
    }

    /**
     * Verifica si una fecha es festiva.
     *
     * @param  Carbon|string  $fecha
     */
    public static function esFestivo($fecha): bool
    {
        return self::getService()->esFestivo(Carbon::parse($fecha));
    }

    /**
     * Obtiene el próximo día hábil a partir de una fecha.
     *
     * @param  Carbon|string  $fecha
     */
    public static function getProximoDiaHabil($fecha): Carbon
    {
        return self::getService()->getProximoDiaHabil(Carbon::parse($fecha));
    }

    /**
     * Obtiene la fecha de vencimiento usando los días por defecto de configuración.
     *
     * @param  Carbon|string  $fechaInicio
     */
    public static function calcularVencimientoPorDefecto($fechaInicio): Carbon
    {
        $dias = ConfigVarias::getDiasVencimientoPredeterminado(5);

        return self::calcularVencimiento($fechaInicio, $dias);
    }

    /**
     * Calcula el vencimiento según la clasificación documental (TRD).
     *
     * @param  Carbon|string  $fechaInicio
     * @param  int  $clasificacionTrdId  ID del elemento en clasificacion_documental_trd
     */
    public static function calcularVencimientoPorClasificacion($fechaInicio, int $clasificacionTrdId): Carbon
    {
        $clasificacion = ClasificacionDocumentalTRD::find($clasificacionTrdId);

        if (! $clasificacion) {
            return self::calcularVencimientoPorDefecto($fechaInicio);
        }

        $dias = $clasificacion->getDiasVencimiento();

        return self::calcularVencimiento($fechaInicio, $dias);
    }
}
