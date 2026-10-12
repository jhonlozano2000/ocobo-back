<?php

namespace App\Console\Commands;

use App\Models\ReporteProgramado;
use App\Services\ReportesProgramadosService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerarReportesProgramadosCommand extends Command
{
    protected $signature = 'reportes:generar-programados';

    protected $description = 'Genera y envía reportes programados vencidos';

    public function handle(ReportesProgramadosService $servicio): int
    {
        // REP-002: sin dueño no hay alcance con qué generarlo; esos quedan fuera
        $programados = ReporteProgramado::where('proxima_ejecucion', '<=', now())
            ->where('activo', true)
            ->whereNotNull('user_id')
            ->with('usuario')
            ->get();

        if ($programados->isEmpty()) {
            $this->info('No hay reportes programados pendientes.');

            return Command::SUCCESS;
        }

        $procesados = 0;
        $errores = 0;

        foreach ($programados as $item) {
            try {
                $servicio->ejecutar($item);

                $procesados++;
                $this->info("Reporte programado {$item->id} procesado.");
            } catch (\Exception $e) {
                $errores++;
                Log::error("Reporte programado {$item->id} falló: {$e->getMessage()}");
                $this->error("Error en reporte {$item->id}: {$e->getMessage()}");
            }
        }

        $this->info("Procesados: {$procesados}, Errores: {$errores}");

        return $errores > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
