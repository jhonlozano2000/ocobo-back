<?php

namespace App\Console\Commands;

use App\Models\Notificacion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Services\OfiArchivo\PendientesArchivoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Decisión D4 (diseño de expedientes §3.4): avisa al responsable de cada expediente abierto sin
 * incorporaciones en N meses. No lo cierra: el cierre exige que el trámite haya terminado.
 * No repite el aviso mientras el anterior siga sin leer.
 */
class AlertarExpedientesInactivosCommand extends Command
{
    protected $signature = 'archivo:alertar-expedientes-inactivos
                            {--meses= : Meses sin actividad (por defecto config archivo.meses_inactividad)}
                            {--dry-run : Solo muestra sin crear notificaciones}';

    protected $description = 'Avisa de expedientes abiertos sin incorporaciones en N meses';

    public const TYPE = 'expediente_inactivo';

    public function handle(): int
    {
        $meses = $this->option('meses') !== null ? (int) $this->option('meses') : PendientesArchivoService::mesesInactividad();
        if ($meses < 1) {
            $this->error('--meses debe ser al menos 1.');

            return Command::FAILURE;
        }

        $avisos = 0;
        PendientesArchivoService::inactivos(OfiArchivoExpediente::query(), $meses)
            ->whereNotNull('usuario_apertura_id')
            ->orderBy('ofi_archivo_expedientes.id')
            ->chunk(200, function ($expedientes) use (&$avisos, $meses) {
                foreach ($expedientes as $expediente) {
                    if ($this->avisoPendiente($expediente->usuario_apertura_id, $expediente->id)) {
                        continue;
                    }

                    $this->line("  {$expediente->numero_expediente} sin actividad desde {$expediente->ultima_actividad} → user#{$expediente->usuario_apertura_id}");
                    if (! $this->option('dry-run')) {
                        $this->notificar($expediente, $meses);
                    }
                    $avisos++;
                }
            });

        $this->info("Expedientes inactivos avisados: {$avisos}");
        Log::info("AlertarExpedientesInactivos: {$avisos} avisos ({$meses} meses)");

        return Command::SUCCESS;
    }

    private function avisoPendiente(int $userId, int $expedienteId): bool
    {
        return Notificacion::where('user_id', $userId)
            ->where('type', self::TYPE)
            ->whereNull('read_at')
            ->whereJsonContains('data->expediente_id', $expedienteId)
            ->exists();
    }

    private function notificar(OfiArchivoExpediente $expediente, int $meses): void
    {
        $desde = substr((string) $expediente->ultima_actividad, 0, 10);

        Notificacion::create([
            'user_id' => $expediente->usuario_apertura_id,
            'type' => self::TYPE,
            'title' => 'Expediente sin actividad',
            'message' => "El expediente {$expediente->numero_expediente} ({$expediente->nombre_expediente}) no tiene incorporaciones desde el {$desde}. "
                .'Si el trámite terminó, ciérrelo; si sigue en curso, incorpore los documentos pendientes.',
            'data' => [
                'expediente_id' => $expediente->id,
                'numero_expediente' => $expediente->numero_expediente,
                'tipo_entidad' => 'expediente',
                'ultima_actividad' => $desde,
                'meses' => $meses,
            ],
        ]);
    }
}
