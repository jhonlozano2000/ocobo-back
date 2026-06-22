<?php

namespace App\Console\Commands;

use App\Services\VentanillaUnica\EmailRadicacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncEmailRadicadosCommand extends Command
{
    protected $signature = 'email:sync-radicados';

    protected $description = 'Sincroniza correos IMAP y genera radicados automáticamente';

    public function __construct(
        private EmailRadicacionService $emailRadicacionService
    ) {
        parent::__construct();
    }

    /**
     * Ejecuta el comando de sincronización de correos radicados.
     */
    public function handle(): int
    {
        $this->info('Iniciando sincronización de correos radicados...');

        try {
            $resultado = $this->emailRadicacionService->sincronizarCorreos();

            Log::info('Sincronización de correos radicados completada', [
                'nuevos' => $resultado['nuevos'] ?? 0,
                'procesados' => $resultado['procesados'] ?? 0,
                'errores' => $resultado['errores'] ?? 0,
            ]);

            $this->info('Sincronización completada exitosamente.');

            if (isset($resultado['nuevos'])) {
                $this->info("Nuevos correos procesados: {$resultado['nuevos']}");
            }

            if (isset($resultado['errores']) && $resultado['errores'] > 0) {
                $this->warn("Correos con errores: {$resultado['errores']}");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Error en sincronización de correos radicados', [
                'error' => $e->getMessage(),
            ]);

            $this->error('Error durante la sincronización: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
