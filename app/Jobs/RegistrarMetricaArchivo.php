<?php

namespace App\Jobs;

use App\Services\Metricas\RegistroMetricasArchivos;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Inserta un evento de métrica de archivo fuera de la petición de carga: en una cola real se
 * encola; con QUEUE_CONNECTION=sync se ejecuta después de enviar la respuesta (patrón del OCR).
 */
class RegistrarMetricaArchivo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public array $fila) {}

    /**
     * Se llama ya después del commit (RegistroMetricasArchivos::registrar usa DB::afterCommit).
     */
    public static function programar(array $fila): void
    {
        if (config('queue.default') === 'sync') {
            dispatch(new self($fila))->afterResponse();

            return;
        }

        dispatch(new self($fila));
    }

    public function handle(RegistroMetricasArchivos $registro): void
    {
        try {
            $registro->insertar($this->fila);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar la métrica del archivo', [
                'archivable' => $this->fila['archivable_type'].'#'.$this->fila['archivable_id'],
                'evento' => $this->fila['evento'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
