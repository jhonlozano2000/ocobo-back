<?php

namespace App\Console\Commands;

use App\Helpers\MailConfigHelper;
use App\Mail\RadicadoEnviadoNotification;
use App\Mail\RadicadoNotification;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Comando para enviar alertas de vencimiento de radicados y PQRS.
 *
 * Se ejecuta diariamente y notifica a los responsables cuando un documento
 * está próximo a vencer (1 o 3 días hábiles).
 * Normativa: Ley 1437/2011 (CPACA), Acuerdo 060/2001 AGN.
 */
class AlertasVencimientoCommand extends Command
{
    protected $signature = 'alertas:vencimiento
                            {--dias=1,3 : Días antes del vencimiento para alertar (separados por coma)}
                            {--dry-run : Solo muestra sin enviar emails}';

    protected $description = 'Envía alertas de vencimiento a responsables de radicados y PQRS';

    public function handle(): int
    {
        $diasStr = $this->option('dias');
        $diasAlerta = collect(explode(',', $diasStr))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0)
            ->values()
            ->all();

        $dryRun = $this->option('dry-run');

        $this->info("Ejecutando alertas de vencimiento para {$diasStr} día(s)...");

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: No se enviarán emails.');
        }

        try {
            MailConfigHelper::configureFromConfigVarias();
        } catch (\Exception $e) {
            Log::error('AlertasVencimiento: Error configurando mail', ['error' => $e->getMessage()]);
            $this->warn('Advertencia al configurar mail: '.$e->getMessage());
        }

        $totalEnviados = 0;

        foreach ($diasAlerta as $dias) {
            $fecha = now()->addDays($dias)->toDateString();

            $totalEnviados += $this->procesarRecibidos($fecha, $dias, $dryRun);
            $totalEnviados += $this->procesarEnviados($fecha, $dias, $dryRun);
            $totalEnviados += $this->procesarPqrs($fecha, $dias, $dryRun);
        }

        $this->info("Alertas completadas. Total emails enviados: {$totalEnviados}");
        Log::info("AlertasVencimiento: {$totalEnviados} alertas enviadas para días: {$diasStr}");

        return Command::SUCCESS;
    }

    /**
     * Procesa radicados recibidos próximos a vencer.
     */
    private function procesarRecibidos(string $fecha, int $dias, bool $dryRun): int
    {
        $radicados = VentanillaRadicaReci::with(['responsables.userCargo.user'])
            ->whereIn('estado_trabajo', ['Pendiente', 'En Proceso'])
            ->whereDate('fec_venci', $fecha)
            ->get();

        $enviados = 0;

        foreach ($radicados as $radicado) {
            $emails = $radicado->responsables
                ->map(fn ($r) => $r->userCargo?->user?->email)
                ->filter()
                ->unique()
                ->values();

            if ($emails->isEmpty()) {
                continue;
            }

            $this->line("  [Recibido] {$radicado->num_radicado} → vence {$fecha} → {$emails->implode(', ')}");

            if (! $dryRun) {
                foreach ($emails as $email) {
                    try {
                        Mail::to($email)->send(new RadicadoNotification($radicado, 'vencimiento'));
                        $enviados++;
                    } catch (\Exception $e) {
                        Log::error("AlertasVencimiento: Error enviando a {$email}", ['error' => $e->getMessage()]);
                    }
                }
            } else {
                $enviados += $emails->count();
            }
        }

        return $enviados;
    }

    /**
     * Procesa radicados enviados próximos a vencer.
     */
    private function procesarEnviados(string $fecha, int $dias, bool $dryRun): int
    {
        $radicados = VentanillaRadicaEnviados::with(['responsables.userCargo.user'])
            ->whereIn('estado_trabajo', ['Pendiente', 'En Proceso'])
            ->whereDate('fec_venci', $fecha)
            ->whereNotNull('fec_venci')
            ->get();

        $enviados = 0;

        foreach ($radicados as $radicado) {
            $emails = $radicado->responsables
                ->map(fn ($r) => $r->userCargo?->user?->email)
                ->filter()
                ->unique()
                ->values();

            if ($emails->isEmpty()) {
                continue;
            }

            $this->line("  [Enviado] {$radicado->num_radicado} → vence {$fecha} → {$emails->implode(', ')}");

            if (! $dryRun) {
                foreach ($emails as $email) {
                    try {
                        Mail::to($email)->send(new RadicadoEnviadoNotification($radicado, 'vencimiento'));
                        $enviados++;
                    } catch (\Exception $e) {
                        Log::error("AlertasVencimiento: Error enviando a {$email}", ['error' => $e->getMessage()]);
                    }
                }
            } else {
                $enviados += $emails->count();
            }
        }

        return $enviados;
    }

    /**
     * Procesa PQRS próximas a vencer.
     */
    private function procesarPqrs(string $fecha, int $dias, bool $dryRun): int
    {
        $pqrsList = VentanillaPqrs::with(['radicado.responsables.userCargo.user'])
            ->whereIn('estado_tramite', ['Pendiente', 'En Tramite'])
            ->whereDate('fecha_vencimiento', $fecha)
            ->get();

        $enviados = 0;

        foreach ($pqrsList as $pqrs) {
            $radicado = $pqrs->radicado;

            if (! $radicado) {
                continue;
            }

            $emails = $radicado->responsables
                ->map(fn ($r) => $r->userCargo?->user?->email)
                ->filter()
                ->unique()
                ->values();

            if ($emails->isEmpty()) {
                continue;
            }

            $numRadi = $radicado->num_radicado ?? "PQRS#{$pqrs->id}";
            $this->line("  [PQRS] {$numRadi} → vence {$fecha} → {$emails->implode(', ')}");

            if (! $dryRun) {
                foreach ($emails as $email) {
                    try {
                        Mail::to($email)->send(new RadicadoNotification($radicado, 'vencimiento'));
                        $enviados++;
                    } catch (\Exception $e) {
                        Log::error("AlertasVencimiento: Error enviando a {$email}", ['error' => $e->getMessage()]);
                    }
                }
            } else {
                $enviados += $emails->count();
            }
        }

        return $enviados;
    }
}
