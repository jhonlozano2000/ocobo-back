<?php

namespace App\Console\Commands;

use App\Services\MiBandeja\GrupoColaborativoService;
use Illuminate\Console\Command;

class LiberarBloqueosGrupos extends Command
{
    protected $signature = 'grupos-colaborativos:liberar-bloqueos';
    protected $description = 'Libera los bloqueos de documentos de grupos colaborativos que hayan expirado (24h)';

    public function handle(GrupoColaborativoService $service): int
    {
        $this->info('Liberando bloqueos expirados de grupos colaborativos...');

        $liberados = $service->liberarBloqueosVencidos();

        $this->info("{$liberados} bloqueo(s) liberado(s) correctamente.");

        return Command::SUCCESS;
    }
}
