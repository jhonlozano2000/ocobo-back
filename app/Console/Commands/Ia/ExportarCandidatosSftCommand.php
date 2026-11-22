<?php

namespace App\Console\Commands\Ia;

use App\Services\Ia\IaAprendizajeContinuoService;
use Illuminate\Console\Command;

/**
 * Comando Artisan para exportar pares calificados como candidatos SFT (HITL F4-03).
 *
 * Filtra documentos con edit_ratio <= 0.40 aprobados por funcionarios y los exporta
 * a formato JSONL para alimentar el pipeline de Replay Buffer anti-catastrophic forgetting.
 */
class ExportarCandidatosSftCommand extends Command
{
    /**
     * El nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'ia:exportar-candidatos-sft
                            {--output= : Ruta del archivo JSONL de salida (por defecto storage/app/ia/candidatos_sft.jsonl)}
                            {--limit=1000 : Límite máximo de candidatos a exportar}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Exporta los pares candidatos SFT aprobados (edit_ratio <= 0.40) para el Replay Buffer LoRA (HITL F4-03)';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(IaAprendizajeContinuoService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $output = $this->option('output') ?: storage_path('app/ia/candidatos_sft.jsonl');

        $this->info('=== OCOBO SGDEA: Exportación de Candidatos SFT para Replay Buffer (F4-03) ===');
        $this->line("Buscando candidatos SFT (edit_ratio <= 0.40) (límite: {$limit})...");

        $resultado = $service->exportarCandidatosSft($output, $limit);

        $total = $resultado['total'] ?? 0;
        if ($total === 0) {
            $this->info('No se encontraron candidatos SFT con contenido final aprobado.');

            return self::SUCCESS;
        }

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total candidatos SFT exportados', number_format($total)],
                ['Ruta de exportación JSONL', $output],
                ['Tamaño del archivo', is_file($output) ? round(filesize($output) / 1024, 2).' KB' : 'N/A'],
            ]
        );

        $this->info('✓ Exportación completada exitosamente.');

        return self::SUCCESS;
    }
}
