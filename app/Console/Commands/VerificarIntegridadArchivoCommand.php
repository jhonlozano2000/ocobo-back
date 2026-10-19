<?php

namespace App\Console\Commands;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Services\OfiArchivo\IntegridadArchivoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * B21 (diseño de expedientes §8): recalcula la huella SHA-256 de una muestra de folios (por defecto
 * el porcentaje de Otras configuraciones → Archivo) o de todos los de un expediente. Los folios
 * alterados, faltantes o sin huella abren un incidente y se avisa al archivo.
 */
class VerificarIntegridadArchivoCommand extends Command
{
    protected $signature = 'archivo:verificar-integridad
                            {--porcentaje= : Porcentaje de folios de la muestra (1-100)}
                            {--expediente= : Id del expediente cuyos folios se verifican todos}';

    protected $description = 'Verifica la huella SHA-256 de una muestra de folios de expedientes';

    public function handle(IntegridadArchivoService $integridad): int
    {
        $porcentaje = $this->option('porcentaje');
        if ($porcentaje !== null && (! ctype_digit((string) $porcentaje) || $porcentaje < 1 || $porcentaje > 100)) {
            $this->error('--porcentaje debe ser un entero entre 1 y 100.');

            return Command::FAILURE;
        }

        if ($this->option('expediente') !== null) {
            $expediente = OfiArchivoExpediente::find((int) $this->option('expediente'));
            if (! $expediente) {
                $this->error('No existe el expediente '.$this->option('expediente').'.');

                return Command::FAILURE;
            }
            $verificacion = $integridad->verificarExpediente($expediente);
        } else {
            $verificacion = $integridad->muestra($porcentaje !== null ? (int) $porcentaje : null);
        }

        $this->info("Folios revisados: {$verificacion->revisados}; con problemas: {$verificacion->incidentes}");
        Log::info("VerificarIntegridadArchivo #{$verificacion->id} ({$verificacion->alcance}): {$verificacion->revisados} revisados, {$verificacion->incidentes} con problemas");

        return Command::SUCCESS;
    }
}
