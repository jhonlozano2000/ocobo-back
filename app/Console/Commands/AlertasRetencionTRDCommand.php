<?php

namespace App\Console\Commands;

use App\Models\Notificacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Comando para alertar cuando la fecha_retencion_fin está próxima a vencer.
 *
 * Evalúa la fecha de retención de los documentos en las tres tablas de metadata
 * (recibidos, enviados, internos) y genera notificaciones in-app cuando los días
 * restantes coinciden con las ventanas configuradas (--dias).
 *
 * Normativa: Decreto 620/2015 (TRD), ISO 15489, ISO 27001 A.8.3.2.
 */
class AlertasRetencionTRDCommand extends Command
{
    protected $signature = 'alertas:retencion-trd
                            {--dias=30,7,1 : Ventanas de alerta en días antes del vencimiento de retención}
                            {--dry-run : Solo muestra sin crear notificaciones}';

    protected $description = 'Alerta cuando fecha_retencion_fin está próxima a vencer';

    private const TYPE = 'retencion_trd';

    private const TABLES = [
        'ventanilla_radica_reci_metadata',
        'ventanilla_radica_enviados_metadata',
        'ventanilla_radica_interno_metadata',
    ];

    private const LABELS = [
        'ventanilla_radica_reci_metadata' => 'Recibido',
        'ventanilla_radica_enviados_metadata' => 'Enviado',
        'ventanilla_radica_interno_metadata' => 'Interno',
    ];

    public function handle(): int
    {
        $diasStr = $this->option('dias');
        $dryRun = $this->option('dry-run');

        $ventanas = collect(explode(',', $diasStr))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0)
            ->values()
            ->all();

        if ($ventanas === []) {
            $this->error('Debe especificar al menos una ventana válida en --dias.');

            return Command::FAILURE;
        }

        rsort($ventanas);

        $this->info('Alertas de retención TRD — ventanas: '.implode(', ', $ventanas).' días');
        if ($dryRun) {
            $this->warn('MODO DRY-RUN: No se crearán notificaciones.');
        }

        $total = 0;

        foreach (self::TABLES as $tabla) {
            $total += $this->procesarTabla($tabla, $ventanas, $dryRun);
        }

        $this->info("Alertas de retención TRD completadas. Total notificaciones: {$total}");
        Log::info("AlertasRetencionTRD: {$total} alertas creadas para ventanas: {$diasStr}");

        return Command::SUCCESS;
    }

    private function procesarTabla(string $tabla, array $ventanas, bool $dryRun): int
    {
        $label = self::LABELS[$tabla];

        $registros = DB::table($tabla)
            ->whereNotNull('fecha_retencion_fin')
            ->where('estado_registro', 'ACTIVO')
            ->whereDate('fecha_retencion_fin', '>=', now()->toDateString())
            ->select(
                'id',
                'radicado_id',
                'num_radicado',
                'asunto',
                'fecha_retencion_fin',
                'dueno_documento_id',
                DB::raw('DATEDIFF(fecha_retencion_fin, CURDATE()) as dias_restantes')
            )
            ->get();

        $enVentanas = $registros->filter(fn ($r) => in_array((int) $r->dias_restantes, $ventanas));

        if ($enVentanas->isEmpty()) {
            $this->line("  [{$label}] Sin documentos próximos a vencer retención.");

            return 0;
        }

        $notificaciones = 0;

        foreach ($enVentanas as $registro) {
            $userIds = $this->obtenerUsuariosNotificar($tabla, $registro);

            if ($userIds === []) {
                $this->line("  [{$label}] {$registro->num_radicado} — sin usuarios responsables para notificar.");

                continue;
            }

            foreach ($userIds as $userId) {
                if ($this->notificacionYaExiste($userId, $registro->id)) {
                    continue;
                }

                $this->line("  [{$label}] {$registro->num_radicado} → {$registro->dias_restantes} días restantes → user#{$userId}");

                if (! $dryRun) {
                    $this->crearNotificacion($userId, $registro, $label);
                }

                $notificaciones++;
            }
        }

        $this->line("  [{$label}] Total notificaciones: {$notificaciones}");

        return $notificaciones;
    }

    private function obtenerUsuariosNotificar(string $tabla, object $registro): array
    {
        if ($registro->dueno_documento_id) {
            return [$registro->dueno_documento_id];
        }

        if (! $registro->radicado_id) {
            return [];
        }

        $radicadoId = $registro->radicado_id;
        $responsableTabla = match ($tabla) {
            'ventanilla_radica_reci_metadata' => 'ventanilla_radica_reci_responsa',
            'ventanilla_radica_enviados_metadata' => 'ventanilla_radica_envia_responsa',
            'ventanilla_radica_interno_metadata' => 'ventanilla_radica_interno_responsa',
            default => null,
        };

        $radicadoColumn = match ($tabla) {
            'ventanilla_radica_reci_metadata' => 'radica_reci_id',
            'ventanilla_radica_enviados_metadata' => 'radica_enviados_id',
            'ventanilla_radica_interno_metadata' => 'radica_interno_id',
            default => null,
        };

        if (! $responsableTabla || ! $radicadoColumn) {
            return [];
        }

        $userIds = DB::table($responsableTabla)
            ->join('users_cargos', 'users_cargos.id', '=', 'users_cargos_id')
            ->where($radicadoColumn, $radicadoId)
            ->pluck('users_cargos.user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $userIds;
    }

    private function notificacionYaExiste(int $userId, int $metadataId): bool
    {
        return Notificacion::where('user_id', $userId)
            ->where('type', self::TYPE)
            ->whereNull('read_at')
            ->whereJsonContains('data->metadata_id', $metadataId)
            ->exists();
    }

    private function crearNotificacion(int $userId, object $registro, string $label): void
    {
        $diasRestantes = (int) $registro->dias_restantes;

        $titulo = match (true) {
            $diasRestantes <= 1 => 'Retención TRD — Vence mañana',
            $diasRestantes <= 7 => "Retención TRD — Vence en {$diasRestantes} días",
            default => "Retención TRD — Vence en {$diasRestantes} días",
        };

        $mensaje = "El documento {$registro->num_radicado} ({$label}) tiene su plazo de retención ".
            "finalizado el {$registro->fecha_retencion_fin}. ".
            "Quedan {$diasRestantes} día(s). Revise la disposición final del documento.";

        Notificacion::create([
            'user_id' => $userId,
            'type' => self::TYPE,
            'title' => $titulo,
            'message' => $mensaje,
            'data' => [
                'metadata_id' => $registro->id,
                'radicado_id' => $registro->radicado_id,
                'num_radicado' => $registro->num_radicado,
                'fecha_retencion_fin' => $registro->fecha_retencion_fin,
                'dias_restantes' => $diasRestantes,
                'ventanilla' => $label,
            ],
        ]);
    }
}
