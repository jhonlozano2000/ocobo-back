<?php

namespace App\Console\Commands;

use App\Models\OfiArchivo\OfiArchivoDepuracionDigital;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Comando de depuración de archivos digitales huérfanos.
 *
 * Detecta archivos en disco que ya no tienen registro en la base de datos
 * y los mueve a una carpeta de cuarentena antes de eliminarlos definitivamente.
 * M08-02: cubre también radicados internos y expedientes; cada movimiento queda en la bitácora
 * `ofi_archivo_depuraciones_digitales` y la cuarentena solo se purga con --purgar-dias (explícito).
 * Normativa: AGN Acuerdo 003/2015, AGN Acuerdo 060/2001, ISO 27001 A.8.3.
 */
class DepuracionDigitalCommand extends Command
{
    protected $signature = 'archivo:depurar-digital
                            {--disco=radicados_recibidos : Disco a depurar (radicados_recibidos|radicados_enviados|radicados_internos|archivo_expedientes)}
                            {--dias=30 : Solo archivos con más de N días sin registro}
                            {--purgar-dias= : En lugar de depurar, elimina la cuarentena con más de N días (mínimo 30)}
                            {--dry-run : Solo muestra sin mover archivos}';

    private const DISCOS = ['radicados_recibidos', 'radicados_enviados', 'radicados_internos', 'archivo_expedientes'];

    private const MIN_DIAS_PURGA = 30;

    protected $description = 'Detecta y mueve a cuarentena archivos digitales huérfanos (sin registro en BD)';

    public function handle(): int
    {
        $disco = $this->option('disco');
        $dias = (int) $this->option('dias');
        $dryRun = $this->option('dry-run');

        if (! in_array($disco, self::DISCOS, true)) {
            $this->error('Disco inválido. Use: '.implode('|', self::DISCOS));

            return Command::FAILURE;
        }

        if ($this->option('purgar-dias') !== null) {
            return $this->purgarCuarentena($disco, (int) $this->option('purgar-dias'), $dryRun);
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
        $rutasEnBd = $this->obtenerRutasEnBd($disco)->flip();

        // M08-01: la carpeta de un radicado existente ({num_radicado}/...) contiene evidencia
        // original (adjuntos de correo, versiones previas) y nunca se depura automáticamente.
        $carpetasRadicados = $this->obtenerCarpetasRadicados($disco)->flip();
        $limite = now()->subDays($dias)->getTimestamp();

        $huerfanos = $archivosEnDisco->filter(function ($archivo) use ($rutasEnBd, $carpetasRadicados, $storage, $limite) {
            // Ignorar archivos en carpeta de cuarentena
            if (str_starts_with($archivo, 'cuarentena/')) {
                return false;
            }

            // Verificar si está en BD
            if ($rutasEnBd->has($archivo)) {
                return false;
            }

            $carpeta = str_contains($archivo, '/') ? strstr($archivo, '/', true) : null;
            if ($carpeta !== null && $carpetasRadicados->has($carpeta)) {
                return false;
            }

            // Verificar antigüedad del archivo
            try {
                return $storage->lastModified($archivo) <= $limite;
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
                    if (! $storage->move($archivo, $destino)) {
                        throw new \RuntimeException('no se pudo mover');
                    }
                    OfiArchivoDepuracionDigital::registrar($disco, 'cuarentena', $archivo, $destino, $storage, $destino, $this->getName());
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

        if ($disco === 'radicados_internos') {
            $principales = VentanillaRadicaInterno::query()->withoutGlobalScopes()->whereNotNull('archivo_digital')
                ->pluck('archivo_digital');

            $adicionales = DB::table('ventanilla_radica_internos_archivos')
                ->whereNotNull('archivo')
                ->pluck('archivo');

            return $principales->merge($adicionales)->filter()->unique()->values();
        }

        if ($disco === 'archivo_expedientes') {
            // Incluye folios retirados: el retiro es lógico y el binario se conserva (M08-06)
            return OfiArchivoExpedienteDocumento::query()->whereNotNull('archivo_path')
                ->pluck('archivo_path')->filter()->unique()->values();
        }

        return collect();
    }

    /**
     * Carpetas de los radicados existentes en el disco (una por num_radicado).
     */
    private function obtenerCarpetasRadicados(string $disco): Collection
    {
        $modelo = match ($disco) {
            'radicados_recibidos' => VentanillaRadicaReci::class,
            'radicados_enviados' => VentanillaRadicaEnviados::class,
            'radicados_internos' => VentanillaRadicaInterno::class,
            default => null,
        };

        if ($modelo === null) {
            return collect();
        }

        return $modelo::query()->withoutGlobalScopes()->whereNotNull('num_radicado')
            ->pluck('num_radicado')->map(fn ($num) => (string) $num)->unique()->values();
    }

    /**
     * Elimina definitivamente las carpetas de cuarentena (cuarentena/{Y-m-d}) con más de N días,
     * registrando cada archivo purgado con su huella antes de borrarlo.
     */
    private function purgarCuarentena(string $disco, int $dias, bool $dryRun): int
    {
        if ($dias < self::MIN_DIAS_PURGA) {
            $this->error('--purgar-dias debe ser al menos '.self::MIN_DIAS_PURGA.'.');

            return Command::FAILURE;
        }

        $storage = Storage::disk($disco);
        $limite = now()->subDays($dias)->startOfDay();
        $purgados = 0;

        foreach ($storage->directories('cuarentena') as $carpeta) {
            $fecha = \DateTime::createFromFormat('!Y-m-d', basename($carpeta));
            if (! $fecha || $fecha >= $limite) {
                continue;
            }

            foreach ($storage->allFiles($carpeta) as $archivo) {
                $this->line("  ✗ {$archivo}");
                if (! $dryRun) {
                    $this->purgarArchivo($storage, $disco, $carpeta, $archivo);
                }
                $purgados++;
            }

            if (! $dryRun && empty($storage->allFiles($carpeta))) {
                $storage->deleteDirectory($carpeta);
            }
        }

        $accion = $dryRun ? 'por purgar' : 'purgados';
        $this->info("Archivos de cuarentena {$accion}: {$purgados}");
        Log::info("DepuracionDigital [{$disco}]: {$purgados} archivos de cuarentena {$accion}", ['dias' => $dias]);

        return Command::SUCCESS;
    }

    private function purgarArchivo(Filesystem $storage, string $disco, string $carpeta, string $archivo): void
    {
        $original = substr($archivo, strlen($carpeta) + 1);
        $registro = OfiArchivoDepuracionDigital::registrar($disco, 'purgado', $original, $archivo, $storage, $archivo, $this->getName());

        if (! $storage->delete($archivo)) {
            $registro->delete();
            $this->warn("    Error eliminando {$archivo}");
        }
    }
}
