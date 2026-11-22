<?php

namespace App\Console\Commands\Ia;

use App\Services\Ia\IaAprendizajeContinuoService;
use Illuminate\Console\Command;

/**
 * Comando Artisan para rotación periódica de logs de auditoría IA cumpliendo ISO 27001 (HITL F4-05).
 */
class RotarLogsAuditoriaIaCommand extends Command
{
    /**
     * El nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'ia:rotar-logs-auditoria
                            {--dias=90 : Días de retención activa antes de archivar (por defecto 90 días)}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Archiva y comprime con hash SHA-256 los logs de auditoría IA mayores a 90 días (ISO 27001 / HITL F4-05)';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(IaAprendizajeContinuoService $service): int
    {
        $dias = max(1, (int) $this->option('dias'));

        $this->info('=== OCOBO SGDEA: Rotación de Logs de Auditoría IA ISO 27001 (F4-05) ===');
        $this->line("Buscando registros con más de {$dias} días de antigüedad...");

        $resultado = $service->rotarLogsAuditoria($dias);

        if (! ($resultado['success'] ?? false)) {
            $this->error('Error durante la rotación: '.($resultado['mensaje'] ?? 'Fallo desconocido'));

            return self::FAILURE;
        }

        $rotados = $resultado['registros_rotados'] ?? 0;
        if ($rotados === 0) {
            $this->info($resultado['mensaje'] ?? 'No hay registros antiguos para rotar.');

            return self::SUCCESS;
        }

        $this->table(
            ['Métrica / Parámetro', 'Valor'],
            [
                ['Registros Archivados', number_format($rotados)],
                ['Días de Retención Activa', "{$dias} días"],
                ['Archivo Comprimido (.json.gz)', $resultado['archivo'] ?? 'N/A'],
                ['Hash de Integridad SHA-256', $resultado['hash_sha256'] ?? 'N/A'],
            ]
        );

        $this->info('✓ Rotación de auditoría ISO 27001 completada exitosamente.');

        return self::SUCCESS;
    }
}
