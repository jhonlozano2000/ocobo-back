<?php

namespace App\Console\Commands;

use App\Models\Metricas\ArchivoMetrica;
use App\Services\Metricas\RegistroMetricasArchivos;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Crea las métricas de los archivos que ya estaban almacenados antes de existir archivos_metricas,
 * con la fecha de creación de cada registro. Se puede repetir: omite los que ya tienen su evento
 * de carga. La dependencia es la del cargo activo actual del usuario (no hay histórico de cargos).
 */
class MetricasArchivosBackfillCommand extends Command
{
    protected $signature = 'metricas:archivos-backfill
                            {--modelo= : Nombre corto del modelo a procesar (p. ej. MiDiscoArchivo)}
                            {--lote=500 : Registros por lote}';

    protected $description = 'Registra en archivos_metricas los archivos ya almacenados';

    public function handle(RegistroMetricasArchivos $registro): int
    {
        $modelos = collect(RegistroMetricasArchivos::MODELOS)
            ->when($this->option('modelo'), fn ($c, $nombre) => $c->filter(fn ($clase) => class_basename($clase) === $nombre));

        if ($modelos->isEmpty()) {
            $this->error('No hay un modelo rastreado con ese nombre.');

            return Command::FAILURE;
        }

        $lote = max(1, (int) $this->option('lote'));
        $total = 0;

        foreach ($modelos as $clase) {
            $creados = 0;
            $query = $clase::query()->withoutGlobalScopes();
            if (in_array(SoftDeletes::class, class_uses_recursive($clase), true)) {
                $query->whereNull((new $clase)->getQualifiedDeletedAtColumn());
            }

            $query->chunkById($lote, function ($registros) use ($registro, &$creados) {
                foreach ($registros as $modelo) {
                    if (! $modelo->archivoAlmacenadoMetrica()) {
                        continue;
                    }
                    $datos = $modelo->datosMetricaArchivo();
                    $fila = $registro->fila(
                        $modelo,
                        ArchivoMetrica::CARGADO,
                        $datos,
                        $datos['fecha'] ?? $modelo->created_at ?? now(),
                        $datos['user_id'] ?? null,
                    );

                    $existe = ArchivoMetrica::query()
                        ->where('archivable_type', $fila['archivable_type'])
                        ->where('archivable_id', $fila['archivable_id'])
                        ->where('ruta_hash', $fila['ruta_hash'])
                        ->where('evento', ArchivoMetrica::CARGADO)
                        ->exists();

                    if (! $existe) {
                        $registro->insertar($fila);
                        $creados++;
                    }
                }
            });

            $this->line(sprintf('%-40s %d', class_basename($clase), $creados));
            $total += $creados;
        }

        $this->info("Métricas creadas: {$total}");

        return Command::SUCCESS;
    }
}
