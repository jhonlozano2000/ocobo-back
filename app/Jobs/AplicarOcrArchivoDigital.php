<?php

namespace App\Jobs;

use App\Services\VentanillaUnica\OcrHttpService;
use App\Services\VentanillaUnica\OcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * M12-03: el OCR del archivo digital de un radicado se ejecutaba dentro del request de subida
 * (y de su transacción), hasta 60 s por archivo. Ahora se aplica en segundo plano: en una cola
 * real se encola tras el commit; con QUEUE_CONNECTION=sync se ejecuta después de enviar la respuesta.
 */
class AplicarOcrArchivoDigital implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * @param  class-string<Model>  $modelo  Radicado con columnas archivo_digital, ocr y ocr_aplicado
     * @param  bool  $respaldoLocal  Si el servicio HTTP no devuelve texto, intenta con Tesseract local
     */
    public function __construct(
        public string $modelo,
        public int $radicadoId,
        public string $disco,
        public string $ruta,
        public bool $respaldoLocal = false,
    ) {}

    /**
     * Programa el OCR del archivo recién subido sin bloquear la respuesta.
     */
    public static function programar(Model $radicado, string $disco, string $ruta, bool $respaldoLocal = false): void
    {
        $job = new self($radicado::class, (int) $radicado->getKey(), $disco, $ruta, $respaldoLocal);

        if (config('queue.default') === 'sync') {
            dispatch($job)->afterResponse();

            return;
        }

        dispatch($job)->afterCommit();
    }

    public function handle(OcrHttpService $ocrHttp, OcrService $ocrLocal): void
    {
        $radicado = $this->modelo::withoutGlobalScopes()->find($this->radicadoId);

        // Si la subida se revirtió o el archivo se reemplazó, el OCR ya no corresponde
        if (! $radicado || $radicado->archivo_digital !== $this->ruta) {
            return;
        }

        try {
            $texto = $ocrHttp->extractText($this->ruta, $this->disco);

            if (! $texto && $this->respaldoLocal && $ocrLocal->isAvailable()) {
                $texto = $ocrLocal->extractText($this->ruta, $this->disco);
            }
        } catch (\Throwable $e) {
            Log::warning('OCR en segundo plano falló', [
                'modelo' => class_basename($this->modelo),
                'radicado_id' => $this->radicadoId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($texto) {
            $radicado->update(['ocr' => $texto, 'ocr_aplicado' => true]);
        }
    }
}
