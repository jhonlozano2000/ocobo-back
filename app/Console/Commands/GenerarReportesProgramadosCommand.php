<?php

namespace App\Console\Commands;

use App\Models\ReporteProgramado;
use App\Services\ReportesExportService;
use App\Services\ReportesUnificadoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReporteProgramadoMailable;
use Illuminate\Support\Facades\Log;

class GenerarReportesProgramadosCommand extends Command
{
    protected $signature = 'reportes:generar-programados';
    protected $description = 'Genera y envía reportes programados vencidos';

    public function handle(ReportesUnificadoService $unificado, ReportesExportService $export): int
    {
        $programados = ReporteProgramado::where('proxima_ejecucion', '<=', now())
            ->where('activo', true)
            ->get();

        if ($programados->isEmpty()) {
            $this->info('No hay reportes programados pendientes.');

            return Command::SUCCESS;
        }

        $procesados = 0;
        $errores = 0;

        foreach ($programados as $item) {
            try {
                $data = $unificado->generarUnificado($item->modulo, $item->filtros ?? []);
                $columnas = $data['columnas'];
                $nombre = 'reporte_' . $item->modulo;

                $path = match ($item->formato) {
                    'excel' => $export->exportarExcel($data, $nombre, $columnas),
                    'pdf' => $export->exportarPDF($data, $nombre),
                    default => null,
                };

                if ($path) {
                    Mail::to($item->destinatarios)->send(new ReporteProgramadoMailable($path, $item->asunto));
                }

                $item->ultima_ejecucion = now();
                $item->proxima_ejecucion = $item->calcularProximaEjecucion();
                $item->save();

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
