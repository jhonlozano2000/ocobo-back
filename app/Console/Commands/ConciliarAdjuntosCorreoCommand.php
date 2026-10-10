<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\VentanillaUnica\EmailRadicacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * M08-01: registra en BD los adjuntos de radicados por correo guardados antes de que
 * existiera el registro automático y, opcionalmente, los devuelve desde la cuarentena.
 */
class ConciliarAdjuntosCorreoCommand extends Command
{
    protected $signature = 'archivo:conciliar-adjuntos-correo
                            {--disco=radicados_recibidos : Disco a conciliar (radicados_recibidos|radicados_enviados)}
                            {--restaurar-cuarentena : Devuelve a la carpeta del radicado los adjuntos movidos a cuarentena}
                            {--usuario= : ID del usuario que queda como responsable del registro}
                            {--dry-run : Solo muestra sin mover ni registrar}';

    protected $description = 'Registra en BD (y restaura de cuarentena) los adjuntos de radicados por correo';

    public function handle(EmailRadicacionService $service): int
    {
        $disco = $this->option('disco');
        $dryRun = (bool) $this->option('dry-run');

        if (! in_array($disco, ['radicados_recibidos', 'radicados_enviados'], true)) {
            $this->error('Disco inválido. Use: radicados_recibidos|radicados_enviados');

            return Command::FAILURE;
        }

        if ($this->option('usuario')) {
            $usuario = User::find($this->option('usuario'));
            if (! $usuario) {
                $this->error('Usuario no encontrado.');

                return Command::FAILURE;
            }
            Auth::setUser($usuario);
        }

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: No se moverán ni registrarán archivos.');
        }

        $storage = Storage::disk($disco);
        $esEnviados = $disco === 'radicados_enviados';
        $tablaArchivos = $esEnviados ? 'ventanilla_radica_enviados_archivos' : 'ventanilla_radica_reci_archivos';
        $columnaRadicado = $esEnviados ? 'radica_enviado_id' : 'radicado_id';
        $modelo = $esEnviados ? VentanillaRadicaEnviados::class : VentanillaRadicaReci::class;

        $cuarentena = $this->option('restaurar-cuarentena')
            ? collect($storage->allFiles('cuarentena'))
            : collect();

        $restaurados = 0;
        $registrados = 0;

        // Radicados creados desde correo: su documento principal es el PDF del correo
        $modelo::query()->withoutGlobalScopes()
            ->where('archivo_digital', 'like', '%/correo-%.pdf')
            ->select(['id', 'num_radicado', 'archivo_digital'])
            ->chunkById(200, function ($radicados) use (
                $service, $storage, $disco, $dryRun, $cuarentena, $tablaArchivos, $columnaRadicado, &$restaurados, &$registrados
            ) {
                foreach ($radicados as $radicado) {
                    $carpeta = (string) $radicado->num_radicado;

                    // cuarentena/{fecha}/{num_radicado}/{archivo}
                    foreach ($cuarentena as $archivo) {
                        $partes = explode('/', $archivo, 3);
                        if (count($partes) < 3 || ! str_starts_with($partes[2], $carpeta.'/')) {
                            continue;
                        }

                        $destino = $partes[2];
                        if ($storage->exists($destino)) {
                            $this->warn("  ! Ya existe {$destino}; se deja en cuarentena {$archivo}");

                            continue;
                        }

                        $this->line("  ↩ {$archivo} → {$destino}");
                        if (! $dryRun) {
                            $storage->move($archivo, $destino);
                        }
                        $restaurados++;
                    }

                    if (! $storage->directoryExists($carpeta)) {
                        continue;
                    }

                    $registradas = DB::table($tablaArchivos)->where($columnaRadicado, $radicado->id)->pluck('archivo')->flip();

                    foreach ($storage->allFiles($carpeta) as $archivo) {
                        if ($archivo === $radicado->archivo_digital || $registradas->has($archivo)) {
                            continue;
                        }

                        $this->line("  + {$archivo}");
                        if (! $dryRun) {
                            $service->registrarAdjunto($radicado, $disco, $archivo, basename($archivo));
                        }
                        $registrados++;
                    }
                }
            });

        $this->info("Adjuntos restaurados de cuarentena: {$restaurados}");
        $this->info("Adjuntos registrados en BD: {$registrados}");

        Log::info("ConciliarAdjuntosCorreo [{$disco}]: {$restaurados} restaurados, {$registrados} registrados", [
            'disco' => $disco,
            'dry_run' => $dryRun,
            'usuario_id' => Auth::id(),
        ]);

        return Command::SUCCESS;
    }
}
