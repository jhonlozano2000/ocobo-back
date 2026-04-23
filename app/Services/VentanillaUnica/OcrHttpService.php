<?php

namespace App\Services\VentanillaUnica;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para el servicio OCR externo (PaddleOCR)
 */
class OcrHttpService
{
    private string $baseUrl;
    private bool $enabled;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.ocr.url', 'http://localhost:5000');
        $this->enabled = config('services.ocr.enabled', false);
        $this->timeout = config('services.ocr.timeout', 60);
    }

    /**
     * Verifica si el servicio OCR está habilitado
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Verifica si el servicio está disponible
     */
    public function isAvailable(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('OCR Service no disponible', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Extrae texto de un archivo
     */
    public function extractText(string $filePath, string $disk = 'radicados_recibidos'): ?string
    {
        if (!$this->isAvailable()) {
            Log::info('OCR: Servicio no disponible');
            return null;
        }

        try {
            $fullPath = \Storage::disk($disk)->path($filePath);

            if (!file_exists($fullPath)) {
                Log::error('OCR: Archivo no encontrado', ['path' => $fullPath]);
                return null;
            }

            $response = Http::timeout($this->timeout)
                ->attach('file', file_get_contents($fullPath), basename($fullPath))
                ->post("{$this->baseUrl}/ocr/extract-text");

            if (!$response->successful()) {
                Log::error('OCR: Error del servicio', [
                    'status' => $response->status(),
                    'body' => $response->body()
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
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $fullPath = \Storage::disk($disk)->path($filePath);

            if (!file_exists($fullPath)) {
                return null;
            }

            $response = Http::timeout($this->timeout)
                ->attach('file', file_get_contents($fullPath), basename($fullPath))
                ->post("{$this->baseUrl}/ocr/extract-structured");

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('OCR Structured: Excepción', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
