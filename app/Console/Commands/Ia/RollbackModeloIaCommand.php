<?php

namespace App\Console\Commands\Ia;

use App\Services\Ia\IaAprendizajeContinuoService;
use Illuminate\Console\Command;

/**
 * Comando Artisan para ejecutar la política de reversión inmediata (Rollback) de modelos IA (HITL F4-04).
 */
class RollbackModeloIaCommand extends Command
{
    /**
     * El nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'ia:rollback-modelo
                            {--modelo=ocobo-asistente:latest : Nombre del modelo base seguro al cual revertir}
                            {--motivo=Rollback administrativo preventivo : Justificación técnica del rollback}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Ejecuta la política de rollback restaurando el modelo seguro en < 10 segundos (HITL F4-04)';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(IaAprendizajeContinuoService $service): int
    {
        $modelo = $this->option('modelo');
        $motivo = $this->option('motivo');

        $this->warn('=== OCOBO SGDEA: Rollback de Emergencia de Modelo IA (F4-04) ===');
        $this->line("Restaurando modelo seguro: {$modelo}...");

        $resultado = $service->ejecutarRollbackModelo($modelo, $motivo);

        if (! ($resultado['success'] ?? false)) {
            $this->error('Fallo ejecutando el rollback: '.($resultado['error'] ?? 'Error desconocido'));

            return self::FAILURE;
        }

        $this->table(
            ['Parámetro', 'Valor'],
            [
                ['Acción', 'ROLLBACK EJECUTADO'],
                ['Modelo Restaurado', $resultado['modelo_restaurado'] ?? $modelo],
                ['Tiempo de Reversión', ($resultado['tiempo_reversion_s'] ?? 0).' s (< 10s SLA)'],
                ['Timestamp', $resultado['timestamp'] ?? now()->toIso8601String()],
                ['Motivo', $motivo],
            ]
        );

        $this->info('✓ Modelo seguro restaurado correctamente.');

        return self::SUCCESS;
    }
}
