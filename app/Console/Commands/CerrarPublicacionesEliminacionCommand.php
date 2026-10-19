<?php

namespace App\Console\Commands;

use App\Services\OfiArchivo\EliminacionDocumentalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * B18 (diseño de expedientes §3.8 E): cierra la publicación de los lotes de eliminación cuyos 60
 * días hábiles terminaron. Con observaciones quedan esperando concepto (30 días hábiles); sin
 * ellas, la aprobación del comité. Avisa a quien registra el comité.
 */
class CerrarPublicacionesEliminacionCommand extends Command
{
    protected $signature = 'archivo:cerrar-publicaciones-eliminacion';

    protected $description = 'Cierra las publicaciones vencidas de los lotes de eliminación documental';

    public function handle(EliminacionDocumentalService $eliminacion): int
    {
        $cerrados = $eliminacion->cerrarPublicaciones();

        $this->info("Publicaciones cerradas: {$cerrados->count()}");
        Log::info("CerrarPublicacionesEliminacion: {$cerrados->count()} lote(s) terminaron su publicación");

        return Command::SUCCESS;
    }
}
