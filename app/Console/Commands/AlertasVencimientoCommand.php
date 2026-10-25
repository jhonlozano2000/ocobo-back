<?php

namespace App\Console\Commands;

use App\Helpers\CalendarioHelper;
use App\Helpers\MailConfigHelper;
use App\Mail\RadicadoEnviadoNotification;
use App\Mail\RadicadoNotification;
use App\Models\ControlAcceso\UserNotificationSetting;
use App\Models\Notificacion;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\VentanillaUnica\RadicadoEstadoTrabajoService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Comando para enviar alertas de vencimiento de radicados y PQRS.
 *
 * Se ejecuta diariamente y avisa a cada responsable cuando un documento está próximo a vencer
 * (1 o 3 días hábiles): notificación en la campana y correo, según sus preferencias de «Vencimientos».
 * Normativa: Ley 1437/2011 (CPACA), Acuerdo 060/2001 AGN.
 */
class AlertasVencimientoCommand extends Command
{
    protected $signature = 'alertas:vencimiento
                            {--dias=1,3 : Días antes del vencimiento para alertar (separados por coma)}
                            {--dry-run : Solo muestra sin notificar ni enviar emails}';

    protected $description = 'Avisa a los responsables de radicados y PQRS próximos a vencer';

    private int $correos = 0;

    private int $notificaciones = 0;

    public function handle(): int
    {
        $diasStr = $this->option('dias');
        $diasAlerta = collect(explode(',', $diasStr))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0)
            ->values()
            ->all();

        $dryRun = (bool) $this->option('dry-run');

        $this->info("Ejecutando alertas de vencimiento para {$diasStr} día(s)...");

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: No se notificará ni se enviarán emails.');
        }

        try {
            MailConfigHelper::configureFromConfigVarias();
        } catch (\Exception $e) {
            Log::error('AlertasVencimiento: Error configurando mail', ['error' => $e->getMessage()]);
            $this->warn('Advertencia al configurar mail: '.$e->getMessage());
        }

        $maxDias = ! empty($diasAlerta) ? max($diasAlerta) : 3;
        $hoy = CalendarioHelper::hoy()->toDateString();
        $fechaMax = CalendarioHelper::sumarDiasHabiles(CalendarioHelper::hoy(), $maxDias)->toDateString();

        $this->info("Buscando documentos con vencimiento entre {$hoy} y {$fechaMax} ({$maxDias} días hábiles)...");

        // Las PQRS son radicados recibidos: se avisan una sola vez, como PQRS
        $recibidosDePqrs = $this->procesarPqrs($hoy, $fechaMax, $dryRun);
        $this->procesarRecibidos($hoy, $fechaMax, $dryRun, $recibidosDePqrs);
        $this->procesarEnviados($hoy, $fechaMax, $dryRun);

        $this->info("Alertas completadas. Notificaciones: {$this->notificaciones}. Emails enviados: {$this->correos}");
        Log::info("AlertasVencimiento: {$this->notificaciones} notificaciones y {$this->correos} emails para ventana: {$hoy} a {$fechaMax}");

        return Command::SUCCESS;
    }

    /**
     * @param  array<int>  $excluir  ids de recibidos ya avisados como PQRS
     */
    private function procesarRecibidos(string $hoy, string $fechaMax, bool $dryRun, array $excluir): void
    {
        $radicados = VentanillaRadicaReci::with(['responsables.userCargo.user'])
            ->where(fn (Builder $q) => $this->abiertos($q))
            ->whereBetween('fec_venci', [$hoy, $fechaMax])
            ->whereNotIn('id', $excluir)
            ->get();

        foreach ($radicados as $radicado) {
            $this->alertar('Recibido', $this->usuarios($radicado), $dryRun, [
                'title' => 'Radicado próximo a vencer',
                'message' => "El radicado {$radicado->num_radicado} vence el {$this->fecha($radicado->fec_venci)}.",
                'notifiable_type' => VentanillaRadicaReci::class,
                'notifiable_id' => $radicado->id,
                'data' => ['radica_reci_id' => $radicado->id, 'num_radicado' => $radicado->num_radicado, 'fecha_vencimiento' => $this->fecha($radicado->fec_venci)],
            ], fn () => new RadicadoNotification($radicado, 'vencimiento'));
        }
    }

    private function procesarEnviados(string $hoy, string $fechaMax, bool $dryRun): void
    {
        $radicados = VentanillaRadicaEnviados::with(['responsables.userCargo.user'])
            ->where(fn (Builder $q) => $this->abiertos($q))
            ->whereNotNull('fec_venci')
            ->whereBetween('fec_venci', [$hoy, $fechaMax])
            ->get();

        foreach ($radicados as $radicado) {
            $this->alertar('Enviado', $this->usuarios($radicado), $dryRun, [
                'title' => 'Radicado próximo a vencer',
                'message' => "El radicado {$radicado->num_radicado} vence el {$this->fecha($radicado->fec_venci)}.",
                'notifiable_type' => VentanillaRadicaEnviados::class,
                'notifiable_id' => $radicado->id,
                'data' => ['radica_enviado_id' => $radicado->id, 'num_radicado' => $radicado->num_radicado, 'fecha_vencimiento' => $this->fecha($radicado->fec_venci)],
            ], fn () => new RadicadoEnviadoNotification($radicado, 'vencimiento'));
        }
    }

    /**
     * @return array<int> ids de los radicados recibidos de las PQRS avisadas
     */
    private function procesarPqrs(string $hoy, string $fechaMax, bool $dryRun): array
    {
        $pqrsList = VentanillaPqrs::with(['radicado.responsables.userCargo.user'])
            ->whereIn('estado_tramite', ['Pendiente', 'En Tramite'])
            ->whereBetween('fecha_vencimiento', [$hoy, $fechaMax])
            ->get();

        $avisados = [];

        foreach ($pqrsList as $pqrs) {
            $radicado = $pqrs->radicado;

            if (! $radicado) {
                continue;
            }

            $avisados[] = $radicado->id;
            $numRadi = $radicado->num_radicado ?? "PQRS#{$pqrs->id}";

            $this->alertar('PQRS', $this->usuarios($radicado), $dryRun, [
                'title' => 'PQRS próxima a vencer',
                'message' => "La PQRS {$numRadi} vence el {$this->fecha($pqrs->fecha_vencimiento)}.",
                'notifiable_type' => VentanillaRadicaReci::class,
                'notifiable_id' => $radicado->id,
                'data' => ['radica_reci_id' => $radicado->id, 'num_radicado' => $numRadi, 'fecha_vencimiento' => $this->fecha($pqrs->fecha_vencimiento)],
            ], fn () => new RadicadoNotification($radicado, 'vencimiento'));
        }

        return $avisados;
    }

    /**
     * Radicados aún en trámite: ni tramitados/finalizados ni anulados.
     */
    private function abiertos(Builder $query): void
    {
        $query->whereNull('estado_trabajo')
            ->orWhereNotIn('estado_trabajo', [...RadicadoEstadoTrabajoService::ESTADOS_TERMINADOS, 'ANULADO']);
    }

    /**
     * @return Collection<int, User>
     */
    private function usuarios(VentanillaRadicaReci|VentanillaRadicaEnviados $radicado): Collection
    {
        return $radicado->responsables
            ->map(fn ($r) => $r->userCargo?->user)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Notifica en la campana (el modelo aplica la preferencia del canal app) y envía el correo si el
     * usuario tiene activo el correo de «Vencimientos».
     *
     * @param  Collection<int, User>  $usuarios
     * @param  callable(): Mailable  $correo
     */
    private function alertar(string $origen, Collection $usuarios, bool $dryRun, array $notificacion, callable $correo): void
    {
        if ($usuarios->isEmpty()) {
            return;
        }

        $this->line("  [{$origen}] {$notificacion['message']} → {$usuarios->pluck('email')->filter()->implode(', ')}");

        if ($dryRun) {
            return;
        }

        foreach ($usuarios as $usuario) {
            $creada = Notificacion::create(['user_id' => $usuario->id, 'type' => Notificacion::TIPO_VENCIMIENTO] + $notificacion);
            $this->notificaciones += $creada->exists ? 1 : 0;

            if (! $usuario->email || ! UserNotificationSetting::canalActivo(UserNotificationSetting::forUser($usuario->id), 'vencimientos', 'email')) {
                continue;
            }

            try {
                Mail::to($usuario->email)->send($correo());
                $this->correos++;
            } catch (\Exception $e) {
                Log::error("AlertasVencimiento: Error enviando a {$usuario->email}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function fecha(mixed $fecha): string
    {
        return $fecha ? Carbon::parse($fecha)->format('d/m/Y') : '';
    }
}
