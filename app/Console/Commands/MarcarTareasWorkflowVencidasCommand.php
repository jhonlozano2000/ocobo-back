<?php

namespace App\Console\Commands;

use App\Services\Workflows\TareaService;
use Illuminate\Console\Command;

/**
 * M19-13: marca como vencidas las tareas de workflow cuya fecha límite pasó, sin esperar
 * a que alguien abra el listado; notifica a los responsables y deja la auditoría (usuario nulo = sistema).
 */
class MarcarTareasWorkflowVencidasCommand extends Command
{
    protected $signature = 'workflows:marcar-tareas-vencidas';

    protected $description = 'Marca como vencidas las tareas de workflow con fecha límite cumplida';

    public function handle(TareaService $tareaService): int
    {
        $total = $tareaService->verificarVencimientosGlobales();
        $this->info("Tareas marcadas como vencidas: {$total}");

        return Command::SUCCESS;
    }
}
