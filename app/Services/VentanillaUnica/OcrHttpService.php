<?php

namespace App\Services\VentanillaUnica;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para el servicio OCR externo (PaddleOCR)
 */
class OcrHttpService
{
    private const HEALTH_TTL = 30;

    private string $baseUrl;

    private bool $enabled;

    private int $timeout;

    private ?string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.ocr.url', 'http://localhost:5000');
        $this->enabled = config('services.ocr.enabled', false);
        $this->timeout = config('services.ocr.timeout', 60);
        $this->token = config('services.ocr.token');
    }

    /**
     * Cliente HTTP con el secreto compartido que exige el microservicio (M12-02)
     */
    private function cliente(int $timeout): PendingRequest
    {
        return Http::timeout($timeout)->withHeaders(['X-OCR-Token' => (string) $this->token]);
    }

    /**
     * Verifica si el servicio OCR está habilitado
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Verifica si el servicio está disponible. M12-03: el resultado se cachea unos segundos
     * para no hacer un health-check HTTP antes de cada extracción.
     */
    public function isAvailable(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return Cache::remember('ocr:health:'.md5($this->baseUrl), self::HEALTH_TTL, function () {
            try {
                return $this->cliente(5)->get("{$this->baseUrl}/health")->successful();
            } catch (\Exception $e) {
                Log::warning('OCR Service no disponible', ['error' => $e->getMessage()]);

                return false;
            }
        });
    }

    /**
     * Extrae texto de un archivo
     */
    public function extractText(string $filePath, string $disk = 'radicados_recibidos'): ?string
    {
        if (! $this->isAvailable()) {
            Log::info('OCR: Servicio no disponible');

            return null;
        }

        try {
            $fullPath = \Storage::disk($disk)->path($filePath);

            if (! file_exists($fullPath)) {
                Log::error('OCR: Archivo no encontrado', ['path' => $fullPath]);

                return null;
            }

            $response = $this->cliente($this->timeout)
                ->attach('file', file_get_contents($fullPath), basename($fullPath))
                ->post("{$this->baseUrl}/ocr/extract-text");

            if (! $response->successful()) {
                Log::error('OCR: Error del servicio', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            return $data['text'] ?? null;
        } catch (\Exception $e) {
            Log::error('OCR: Excepción', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Extrae texto y datos estructurados
     */
    public function extractStructuredData(string $filePath, string $disk = 'radicados_recibidos'): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            $fullPath = \Storage::disk($disk)->path($filePath);

            if (! file_exists($fullPath)) {
                return null;
            }

            $response = $this->cliente($this->timeout)
                ->attach('file', file_get_contents($fullPath), basename($fullPath))
                ->post("{$this->baseUrl}/ocr/extract-structured");

            if (! $response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('OCR Structured: Excepción', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
