<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\ConfigCalendarioFestivo;
use App\Models\Configuracion\ConfigVarias;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BusinessDaysService
{
    private const CACHE_KEY_FESTIVOS = 'festivos_anio_';
    private const CACHE_TTL_MINUTES = 60;

    public function __construct()
    {
    }

    public function getFestivos(int $anio): Collection
    {
        $cacheKey = self::CACHE_KEY_FESTIVOS . $anio;

        return Cache::remember($cacheKey, self::CACHE_TTL_MINUTES * 60, function () use ($anio) {
            return ConfigCalendarioFestivo::where('anio', $anio)
                ->orWhereRaw('YEAR(fecha) = ?', [$anio])
                ->get();
        });
    }

    public function getFestivosRango(Carbon $fechaInicio, Carbon $fechaFin): Collection
    {
        return Cache::remember(
            "festivos_rango_{$fechaInicio->format('Y-m-d')}_{$fechaFin->format('Y-m-d')}",
            self::CACHE_TTL_MINUTES * 60,
            fn () => ConfigCalendarioFestivo::whereBetween('fecha', [
                $fechaInicio->format('Y-m-d'),
                $fechaFin->format('Y-m-d')
            ])->get()
        );
    }

    public function calcularVencimiento(Carbon $fechaInicio, int $diasHabiles): Carbon
    {
        $fecha = $fechaInicio->copy();
        $contador = 0;

        while ($contador < $diasHabiles) {
            $fecha->addDay();

            if (!$this->esDiaHabil($fecha)) {
                continue;
            }

            $contador++;
        }

        return $fecha;
    }

    public function diasHabilesEntre(Carbon $fechaInicio, Carbon $fechaFin): int
    {
        if ($fechaInicio->greaterThan($fechaFin)) {
            return 0;
        }

        $festivos = $this->getFestivosRango($fechaInicio, $fechaFin)
            ->pluck('fecha')
            ->map(fn($f) => Carbon::parse($f)->format('Y-m-d'))
            ->toArray();

        $dias = 0;
        $temp = $fechaInicio->copy();

        while ($temp->lessThanOrEqualTo($fechaFin)) {
            if ($this->esDiaHabil($temp, $festivos)) {
                $dias++;
            }
            $temp->addDay();
        }

        return $dias;
    }

    public function diasHabilesRestantes(Carbon $fechaVencimiento): int
    {
        $hoy = Carbon::today();

        if ($hoy->greaterThan($fechaVencimiento)) {
            return 0;
        }

        return $this->diasHabilesEntre($hoy, $fechaVencimiento) - 1;
    }

    public function esDiaHabil(Carbon $fecha, ?array $festivosExtras = null): bool
    {
        if ($fecha->isWeekend()) {
            return false;
        }

        $considerarFestivos = ConfigVarias::getConsiderarDiasHabiles(true);

        if (!$considerarFestivos) {
            return true;
        }

        $festivos = $festivosExtras ?? $this->getFestivosPorFecha($fecha);

        return !in_array($fecha->format('Y-m-d'), $festivos);
    }

    public function getFestivosPorFecha(Carbon $fecha): array
    {
        return $this->getFestivos($fecha->year)
            ->pluck('fecha')
            ->map(fn($f) => Carbon::parse($f)->format('Y-m-d'))
            ->toArray();
    }

    public function esFestivo(Carbon $fecha): bool
    {
        return ConfigCalendarioFestivo::where('fecha', $fecha->format('Y-m-d'))->exists();
    }

    public function getProximoDiaHabil(Carbon $fecha): Carbon
    {
        $temp = $fecha->copy();

        while (!$this->esDiaHabil($temp)) {
            $temp->addDay();
        }

        return $temp;
    }

    public function getAnteriorDiaHabil(Carbon $fecha): Carbon
    {
        $temp = $fecha->copy();

        while (!$this->esDiaHabil($temp)) {
            $temp->subDay();
        }

        return $temp;
    }

    public function clearCache(?int $anio = null): void
    {
        if ($anio) {
            Cache::forget(self::CACHE_KEY_FESTIVOS . $anio);
        } else {
            for ($y = 2020; $y <= 2030; $y++) {
                Cache::forget(self::CACHE_KEY_FESTIVOS . $y);
            }
        }
    }

    public function importarFestivos(array $festivos): array
    {
        $resultados = [
            'creados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        foreach ($festivos as $festivo) {
            try {
                $fecha = Carbon::parse($festivo['fecha'])->format('Y-m-d');
                $existe = ConfigCalendarioFestivo::where('fecha', $fecha)->exists();

                if ($existe) {
                    $resultados['omitidos']++;
                    continue;
                }

                ConfigCalendarioFestivo::create([
                    'fecha' => $fecha,
                    'nombre' => $festivo['nombre'] ?? 'Festivo importado',
                    'tipo' => $festivo['tipo'] ?? 'Nacional',
                    'anio' => Carbon::parse($fecha)->year,
                ]);

                $resultados['creados']++;
            } catch (\Exception $e) {
                $resultados['errores'][] = [
                    'fecha' => $festivo['fecha'] ?? 'desconocida',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->clearCache();

        return $resultados;
    }

    public function generarFestivosColombia(int $anio): array
    {
        $festivosColombia = $this->getFestivosNacionalesColombia($anio);

        return $this->importarFestivos($festivosColombia);
    }

    public function getFestivosNacionalesColombia(int $anio): array
    {
        $easterDate = $this->calculateEaster($anio);

        $festivos = [
            ['fecha' => "{$anio}-01-01", 'nombre' => 'Año Nuevo', 'tipo' => 'Nacional'],
            ['fecha' => "{$anio}-05-01", 'nombre' => 'Día del Trabajo', 'tipo' => 'Nacional'],
            ['fecha' => "{$anio}-07-20", 'nombre' => 'Día de la Independencia', 'tipo' => 'Nacional'],
            ['fecha' => "{$anio}-08-07", 'nombre' => 'Batalla de Boyacá', 'tipo' => 'Nacional'],
            ['fecha' => "{$anio}-12-08", 'nombre' => 'Inmaculada Concepción', 'tipo' => 'Nacional'],
            ['fecha' => "{$anio}-12-25", 'nombre' => 'Navidad', 'tipo' => 'Nacional'],
        ];

        $juevesSanto = $easterDate->copy()->subDays(3);
        $viernesSanto = $easterDate->copy()->subDays(2);
        $ascension = $easterDate->copy()->addDays(39);
        $corpusChristi = $easterDate->copy()->addDays(60);
        $sagradoCorazon = $easterDate->copy()->addDays(68);

        $festivos[] = ['fecha' => $juevesSanto->format('Y-m-d'), 'nombre' => 'Jueves Santo', 'tipo' => 'Nacional'];
        $festivos[] = ['fecha' => $viernesSanto->format('Y-m-d'), 'nombre' => 'Viernes Santo', 'tipo' => 'Nacional'];
        $festivos[] = ['fecha' => $ascension->format('Y-m-d'), 'nombre' => 'Ascensión del Señor', 'tipo' => 'Nacional'];
        $festivos[] = ['fecha' => $corpusChristi->format('Y-m-d'), 'nombre' => 'Corpus Christi', 'tipo' => 'Nacional'];
        $festivos[] = ['fecha' => $sagradoCorazon->format('Y-m-d'), 'nombre' => 'Sagrado Corazón', 'tipo' => 'Nacional'];

        usort($festivos, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));

        return $festivos;
    }

    private function calculateEaster(int $year): Carbon
    {
        $a = $year % 19;
        $b = floor($year / 100);
        $c = $year % 100;
        $d = floor($b / 4);
        $e = $b % 4;
        $f = floor(($b + 8) / 25);
        $g = floor(($b - $f + 1) / 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = floor($c / 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = floor(($a + 11 * $h + 22 * $l) / 451);
        $month = floor(($h + $l - 7 * $m + 114) / 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::createFromDate($year, $month, $day);
    }
}
