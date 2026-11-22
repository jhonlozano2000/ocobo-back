<?php

namespace App\Console\Commands\Ia;

use App\Services\Ia\IaAprendizajeContinuoService;
use Illuminate\Console\Command;

/**
 * Comando Artisan para emitir la revisión periódica de privacidad Ley 1581 / Res. 1995 (HITL F4-05).
 */
class ReportePrivacidadLey1581Command extends Command
{
    /**
     * El nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'ia:reporte-privacidad-ley1581
                            {--periodo= : Periodo o trimestre a auditar (ej: 2026-Q4)}
                            {--limite=200 : Muestra máxima de registros a auditar}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Audita el cumplimiento estricto de privacidad Ley 1581 y reserva de salud Res. 1995 (HITL F4-05)';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(IaAprendizajeContinuoService $service): int
    {
        $periodo = $this->option('periodo');
        $limite = max(1, (int) $this->option('limite'));

        $this->info('=== OCOBO SGDEA: Auditoría Periódica de Privacidad Ley 1581 (F4-05) ===');
        $this->line("Auditando últimos {$limite} registros generados...");

        $resultado = $service->generarReportePrivacidadLey1581($periodo, $limite);

        if (! ($resultado['success'] ?? false)) {
            $this->error('Error durante la auditoría: '.($resultado['error'] ?? 'Fallo de conexión'));

            return self::FAILURE;
        }

        $fugas = $resultado['fugas_pii_detectadas'] ?? 0;
        $tasa = $resultado['tasa_cumplimiento_pct'] ?? 0.0;
        $estado = $fugas === 0 ? '🟢 100% CONFORME (0% FUGAS PII)' : '🔴 ALERTA: FUGAS DETECTADAS';

        $this->table(
            ['Métrica de Privacidad', 'Valor'],
            [
                ['Periodo Auditado', $resultado['periodo'] ?? 'N/A'],
                ['Total Textos Auditados', number_format($resultado['total_textos_auditados'] ?? 0)],
                ['Textos Conformes (Cero PII)', number_format($resultado['textos_conformes_cero_pii'] ?? 0)],
                ['Fugas PII Crudas Detectadas', number_format($fugas)],
                ['Tasa de Cumplimiento', "{$tasa}%"],
                ['Alertas Historia Clínica Resguardadas', number_format($resultado['alertas_hc_resguardadas'] ?? 0)],
                ['Alertas Tutela Resguardadas', number_format($resultado['alertas_tutela_resguardadas'] ?? 0)],
                ['Dictamen Final', $estado],
            ]
        );

        if (! empty($resultado['detalles_hallazgos'])) {
            $this->warn('Hallazgos puntuales:');
            foreach ($resultado['detalles_hallazgos'] as $h) {
                $this->line("  ⚠️ {$h}");
            }
        }

        return $fugas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
