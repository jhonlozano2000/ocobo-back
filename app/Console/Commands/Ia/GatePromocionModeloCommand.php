<?php

namespace App\Console\Commands\Ia;

use App\Services\Ia\IaAprendizajeContinuoService;
use Illuminate\Console\Command;

/**
 * Comando Artisan para evaluar candidatos LoRA a través del Gate de Promoción (HITL F4-04).
 */
class GatePromocionModeloCommand extends Command
{
    /**
     * El nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'ia:gate-promocion
                            {--candidato= : Ruta al archivo JSON con el reporte del modelo candidato}
                            {--baseline= : Ruta opcional al archivo JSON del baseline en producción}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Evalúa de forma automatizada un modelo LoRA candidato contra el Golden Test Set y baseline (HITL F4-04)';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(IaAprendizajeContinuoService $service): int
    {
        $rutaCandidato = $this->option('candidato');
        if (! $rutaCandidato || ! file_exists($rutaCandidato)) {
            $this->error("Ruta de reporte del candidato no válida o archivo inexistente: {$rutaCandidato}");

            return self::FAILURE;
        }

        $candidatoJson = json_decode(file_get_contents($rutaCandidato), true);
        $reporteCandidato = $candidatoJson['reporte_global'] ?? $candidatoJson;

        $reporteBaseline = null;
        $rutaBaseline = $this->option('baseline');
        if ($rutaBaseline && file_exists($rutaBaseline)) {
            $baselineJson = json_decode(file_get_contents($rutaBaseline), true);
            $reporteBaseline = $baselineJson['reporte_global'] ?? $baselineJson;
        }

        $this->info('=== OCOBO SGDEA: Gate Automatizado de Promoción LoRA (F4-04) ===');
        $this->line("Evaluando modelo: {$reporteCandidato['modelo']}...");

        $resultado = $service->evaluarGatePromocion($reporteCandidato, $reporteBaseline);

        if (! ($resultado['success'] ?? false)) {
            $this->error('Error ejecutando el gate: '.($resultado['error'] ?? 'Fallo de conexión'));

            return self::FAILURE;
        }

        $aprobado = $resultado['aprobado'] ?? false;
        $accion = $resultado['accion'] ?? 'BLOQUEAR';

        $this->table(
            ['Criterio / Métrica', 'Resultado'],
            [
                ['Modelo Candidato', $resultado['modelo_candidato'] ?? 'N/A'],
                ['Veredicto', $aprobado ? '🟢 PROMOVER A PRODUCCIÓN' : '🔴 BLOQUEADO'],
                ['Acción', $accion],
                ['Fecha de Decisión', $resultado['fecha_decision'] ?? now()->toIso8601String()],
            ]
        );

        if (! empty($resultado['motivos_bloqueo'])) {
            $this->error('Motivos de Bloqueo:');
            foreach ($resultado['motivos_bloqueo'] as $motivo) {
                $this->line("  ❌ {$motivo}");
            }
        }

        if (! empty($resultado['advertencias'])) {
            $this->warn('Advertencias:');
            foreach ($resultado['advertencias'] as $adv) {
                $this->line("  ⚠️ {$adv}");
            }
        }

        return $aprobado ? self::SUCCESS : self::FAILURE;
    }
}
