<?php

namespace App\Console\Commands;

use App\Services\OfiArchivo\DisposicionFinalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * B22 (diseño de expedientes §3.8): pasa a disposición final los expedientes cerrados en el
 * archivo central cuya retención total ya se cumplió, con el destino de la TRD del cierre, y
 * avisa a quien tiene Disposicion -> Ver.
 */
class MarcarDisposicionFinalCommand extends Command
{
    protected $signature = 'archivo:marcar-disposicion';

    protected $description = 'Pasa a disposición final los expedientes del archivo central con la retención cumplida';

    public function handle(DisposicionFinalService $disposicion): int
    {
        $marcados = $disposicion->marcarCumplidos();

        $this->info("Expedientes en disposición final: {$marcados->count()}");
        Log::info("MarcarDisposicionFinal: {$marcados->count()} expediente(s) pasaron a disposición final");

        return Command::SUCCESS;
    }
}
