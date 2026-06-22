<?php

namespace App\Console\Commands;

use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Comando de depuración de archivos digitales huérfanos.
 *
 * Detecta archivos en disco que ya no tienen registro en la base de datos
 * y los mueve a una carpeta de cuarentena antes de eliminarlos definitivamente.
 * Normativa: AGN Acuerdo 003/2015, AGN Acuerdo 060/2001, ISO 27001 A.8.3.
 */
class DepuracionDigitalCommand extends Command
{
    protected $signature = 'archivo:depurar-digital
                            {--disco=radicados_recibidos : Disco a depurar (radicados_recibidos|radicados_enviados)}
                            {--dias=30 : Solo archivos con más de N días sin registro}
                            {--dry-run : Solo muestra sin mover archivos}';

    protected $description = 'Detecta y mueve a cuarentena archivos digitales huérfanos (sin registro en BD)';

    public function handle(): int
    {
        $disco = $this->option('disco');
        $dias = (int) $this->option('dias');
        $dryRun = $this->option('dry-run');

        $discosValidos = ['radicados_recibidos', 'radicados_enviados'];
        if (! in_array($disco, $discosValidos)) {
            $this->error('Disco inválido. Use: '.implode('|', $discosValidos));

            return Command::FAILURE;
        }

        $this->info("Depurando archivos en disco: {$disco} (más de {$dias} días sin registro)");

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: No se moverán archivos.');
        }

        $storage = Storage::disk($disco);

        // Obtener todos los archivos del disco
        try {
            $archivosEnDisco = collect($storage->allFiles());
        } catch (\Exception $e) {
            $this->error('Error listando archivos: '.$e->getMessage());

            return Command::FAILURE;
        }

        if ($archivosEnDisco->isEmpty()) {
            $this->info('No hay archivos en el disco.');

            return Command::SUCCESS;
        }

        $this->info("Total archivos en disco: {$archivosEnDisco->count()}");

        // Obtener rutas registradas en BD
        $rutasEnBd = $this->obtenerRutasEnBd($disco);

        $huerfanos = $archivosEnDisco->filter(function ($archivo) use ($rutasEnBd, $storage, $dias) {
            // Ignorar archivos en carpeta de cuarentena
            if (str_starts_with($archivo, 'cuarentena/')) {
                return false;
            }

            // Verificar si está en BD
            if ($rutasEnBd->contains($archivo)) {
                return false;
            }

            // Verificar antigüedad del archivo
            try {
                $ultimaModificacion = $storage->lastModified($archivo);
                $diasDesdeModificacion = now()->diffInDays(now()->createFromTimestamp($ultimaModificacion));

                return $diasDesdeModificacion >= $dias;
            } catch (\Exception $e) {
                return false;
            }
        });

        if ($huerfanos->isEmpty()) {
            $this->info('No se encontraron archivos huérfanos.');

            return Command::SUCCESS;
        }

        $this->warn("Archivos huérfanos encontrados: {$huerfanos->count()}");

        $movidos = 0;

        foreach ($huerfanos as $archivo) {
            $destino = 'cuarentena/'.now()->format('Y-m-d').'/'.$archivo;
            $this->line("  → {$archivo}");

            if (! $dryRun) {
                try {
                    // Mover a cuarentena (no eliminar directamente — auditoría ISO 27001)
                    $storage->move($archivo, $destino);
                    $movidos++;
                } catch (\Exception $e) {
                    $this->warn("    Error moviendo {$archivo}: ".$e->getMessage());
                }
            } else {
                $movidos++;
            }
        }

        $accion = $dryRun ? 'detectados' : 'movidos a cuarentena';
        $this->info("Archivos {$accion}: {$movidos}");

        Log::info("DepuracionDigital [{$disco}]: {$movidos} archivos {$accion}", [
            'disco' => $disco,
            'dias_umbral' => $dias,
            'dry_run' => $dryRun,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Obtiene todas las rutas de archivos digitales registradas en BD para el disco dado.
     */
    private function obtenerRutasEnBd(string $disco): Collection
    {
        if ($disco === 'radicados_recibidos') {
            $principales = VentanillaRadicaReci::whereNotNull('archivo_digital')
                ->pluck('archivo_digital');

            $adicionales = \DB::table('ventanilla_radica_reci_archivos')
                ->whereNotNull('archivo')
                ->pluck('archivo');

            return $principales->merge($adicionales)->filter()->unique()->values();
        }

        if ($disco === 'radicados_enviados') {
            $principales = VentanillaRadicaEnviados::whereNotNull('archivo_digital')
                ->pluck('archivo_digital');

            $adicionales = \DB::table('ventanilla_radica_enviados_archivos')
                ->whereNotNull('archivo')
                ->pluck('archivo');

            return $principales->merge($adicionales)->filter()->unique()->values();
        }

        return collect();
    }
}
