<?php

namespace App\Console\Commands;

use App\Services\OfiArchivo\ExpedienteEventosService;
use Illuminate\Console\Command;

/**
 * B12 (diseño de expedientes): recalcula la cadena de huellas del historial de los expedientes
 * y reporta cualquier evento alterado, faltante o fuera de orden.
 */
class VerificarCadenaExpedientesCommand extends Command
{
    protected $signature = 'archivo:verificar-cadena
                            {--expediente= : Verifica solo el historial de este expediente}';

    protected $description = 'Verifica la integridad del historial encadenado de los expedientes';

    public function handle(ExpedienteEventosService $eventos): int
    {
        if (! ExpedienteEventosService::disponible()) {
            $this->error('Falta la migración del historial de expedientes (2026_10_19_100000).');

            return self::FAILURE;
        }

        $expediente = $this->option('expediente');
        $rupturas = $eventos->verificar($expediente ? (int) $expediente : null);

        if ($rupturas === []) {
            $this->info('La cadena del historial está íntegra.');

            return self::SUCCESS;
        }

        $this->error(count($rupturas).' problema(s) en la cadena del historial:');
        $this->table(['Expediente', 'Evento', 'Problema'], array_map(fn ($r) => array_values($r), $rupturas));

        return self::FAILURE;
    }
}
