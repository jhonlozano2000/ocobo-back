<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Configuracion\BusinessDaysService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * M13-02/03: corrige los festivos nacionales ya guardados con el generador anterior
 * (sin Ley Emiliani y con 7 festivos faltantes) y crea los que faltan.
 */
class SincronizarFestivosColombiaCommand extends Command
{
    protected $signature = 'festivos:sincronizar-colombia
        {anio* : Uno o más años, p. ej. 2026 2027}
        {--usuario= : ID del usuario responsable (queda en la bitácora de actividad)}';

    protected $description = 'Regenera los festivos nacionales de Colombia (Ley Emiliani) corrigiendo fechas erradas';

    public function handle(BusinessDaysService $service): int
    {
        $usuario = $this->option('usuario') ? User::find($this->option('usuario')) : null;

        if (! $usuario) {
            $this->error('Indique un usuario válido con --usuario=ID (requerido por la bitácora de actividad).');

            return self::FAILURE;
        }

        Auth::setUser($usuario);

        foreach ($this->argument('anio') as $anio) {
            if (! ctype_digit((string) $anio) || $anio < 2000 || $anio > 2100) {
                $this->error("Año inválido: {$anio}");

                return self::FAILURE;
            }

            $r = $service->generarFestivosColombia((int) $anio);
            $this->info("{$anio}: {$r['corregidos']} corregidos (eliminados), {$r['creados']} creados, {$r['omitidos']} ya existían, ".count($r['errores']).' errores');

            foreach ($r['errores'] as $error) {
                $this->warn("  {$error['fecha']}: {$error['error']}");
            }
        }

        return self::SUCCESS;
    }
}
