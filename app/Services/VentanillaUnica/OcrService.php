<?php

namespace App\Services\VentanillaUnica;

use App\Helpers\ArchivoHelper;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio para extracción de texto OCR de documentos
 * Usa Tesseract OCR con soporte para español
 */
class OcrService
{
    private const SUPPORTED_TYPES = ['pdf', 'png', 'jpg', 'jpeg', 'tiff', 'tif', 'bmp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'odt', 'ods', 'odp'];
    private const PSM_MODES = [
        'auto' => '--psm 3',
        'single_block' => '--psm 6',
        'single_line' => '--psm 7',
        'single_word' => '--psm 8',
        'single_char' => '--psm 10',
    ];

    /**
     * Aplica OCR a un archivo y retorna el texto extraído
     */
    public function extractText(string $filePath, string $disk = 'radicados_recibidos'): ?string
    {
        try {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (!in_array($extension, self::SUPPORTED_TYPES)) {
                Log::warning("OCR: Tipo de archivo no soportado ({$extension})");
                return null;
            }

            $fullPath = Storage::disk($disk)->path($filePath);

            if (!file_exists($fullPath)) {
                Log::error("OCR: Archivo no encontrado: {$fullPath}");
                return null;
            }

            if ($extension === 'pdf') {
                return $this->extractFromPdf($fullPath);
            }

            return $this->applyOcr($fullPath);
        } catch (\Exception $e) {
            Log::error('OCR Error: ' . $e->getMessage(), [
                'file' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Aplica OCR usando Tesseract
     */
    private function applyOcr(string $imagePath, string $psmMode = 'auto'): ?string
    {
        $command = $this->buildTesseractCommand($imagePath, $psmMode);

        Log::info("OCR: Ejecutando comando: {$command}");

        $output = [];
        $returnCode = 0;

        exec($command . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error("OCR: Tesseract falló con código {$returnCode}", ['output' => $output]);
            return null;
        }

        $text = implode("\n", $output);
        return $this->cleanText($text);
    }

    /**
     * Construye el comando Tesseract
     */
    private function buildTesseractCommand(string $imagePath, string $psmMode): string
    {
        $tesseract = config('services.tesseract.path', 'tesseract');
        $language = config('services.tesseract.language', 'spa+eng');
        $psm = self::PSM_MODES[$psmMode] ?? self::PSM_MODES['auto'];

        return "\"{$tesseract}\" " . escapeshellarg($imagePath) . " stdout -l {$language} {$psm}";
    }

    /**
     * Extrae texto de PDF usando ImageMagick + Tesseract
     */
    private function extractFromPdf(string $pdfPath): ?string
    {
        try {
            // Método 1: Intentar con Ghostscript
            $text = $this->extractFromPdfWithImageMagick($pdfPath, null);
            if ($text) {
                return $text;
            }

            // Método 2: Intentar con Poppler (pdftoppm)
            $text = $this->extractFromPdfWithPoppler($pdfPath, null);
            if ($text) {
                return $text;
            }

            Log::warning("OCR: No se pudo convertir el PDF. Instale Ghostscript o pdftoppm.");
            return null;
        } catch (\Exception $e) {
            Log::error("OCR: Error al procesar PDF: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Elimina un directorio de forma segura
     */
    private function safeRemoveDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        
        @rmdir($dir);
    }

    /**
     * Extrae texto de PDF usando Ghostscript directamente
     */
    private function extractFromPdfWithImageMagick(string $pdfPath, string $tempDir): ?string
    {
        // Usar un directorio temporal dentro de storage/app que es escribible
        $ocrTempDir = storage_path('app/ocr_temp/' . uniqid('ocr_'));
        if (!is_dir($ocrTempDir)) {
            mkdir($ocrTempDir, 0755, true);
        }

        // Buscar Ghostscript en rutas comunes
        $ghostscriptPaths = [
            'C:\\Program Files\\gs\\gs10.07.0\\bin',
            'C:\\Program Files\\gs\\gs10.03.1\\bin',
            'C:\\Program Files\\Ghostscript\\bin',
        ];

        $gsPath = null;
        foreach ($ghostscriptPaths as $path) {
            if (is_dir($path)) {
                $gsPath = $path;
                break;
            }
        }

        if (!$gsPath) {
            Log::warning("OCR: Ghostscript no encontrado");
            $this->safeRemoveDir($ocrTempDir);
            return null;
        }

        $gsExe = $gsPath . '\\gswin64c.exe';

        // Usar forward slashes para compatibilidad
        $outputFile = $ocrTempDir . '/page_%03d.png';
        $command = sprintf(
            '"%s" -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r150 -sOutputFile="%s" -f "%s"',
            $gsExe,
            $outputFile,
            $pdfPath
        );

        Log::info("OCR: Comando Ghostscript: {$command}");

        $output = [];
        $returnCode = 0;
        exec($command . " 2>&1", $output, $returnCode);

        Log::info("OCR: Ghostscript output", ['output' => $output, 'returnCode' => $returnCode]);

        if ($returnCode !== 0) {
            Log::warning("OCR: Ghostscript falló", ['output' => $output, 'returnCode' => $returnCode]);
            $this->safeRemoveDir($ocrTempDir);
            return null;
        }

        // Verificar si se crearon las páginas
        $pages = glob($ocrTempDir . '/page_*.png');
        Log::info("OCR: Páginas generadas", ['count' => count($pages), 'dir' => $ocrTempDir, 'pages' => $pages]);

        if (empty($pages)) {
            Log::warning("OCR: Ghostscript no generó páginas");
            $this->safeRemoveDir($ocrTempDir);
            return null;
        }

        // Procesar las páginas
        $text = $this->processPdfPagesFromDir($ocrTempDir);

        // Limpiar
        $this->safeRemoveDir($ocrTempDir);

        return $text;
    }

    /**
     * Procesa las páginas convertidas y aplica OCR
     */
    private function processPdfPagesFromDir(string $dir): ?string
    {
        $pages = glob($dir . '/page_*.png');
        
        if (empty($pages)) {
            return null;
        }

        Log::info("OCR: Procesando páginas", ['count' => count($pages)]);

        $allText = [];
        foreach ($pages as $pageNum => $pagePath) {
            $pageText = $this->applyOcr($pagePath);
            if ($pageText) {
                $allText[] = "--- Página " . ($pageNum + 1) . " ---";
                $allText[] = $pageText;
            }
        }

        return empty($allText) ? null : implode("\n\n", $allText);
    }

    /**
     * Extrae texto de PDF usando Poppler (pdftoppm)
     */
    private function extractFromPdfWithPoppler(string $pdfPath, ?string $tempDir): ?string
    {
        // Buscar pdftoppm en rutas comunes de Windows
        $popplerPaths = [
            'C:\\poppler\\Library\\bin\\pdftoppm.exe',
            'C:\\Program Files\\poppler\\bin\\pdftoppm.exe',
            'C:\\poppler\\bin\\pdftoppm.exe',
        ];

        $pdftoppm = null;
        foreach ($popplerPaths as $path) {
            if (file_exists($path)) {
                $pdftoppm = $path;
                break;
            }
        }

        if (!$pdftoppm) {
            Log::warning("OCR: pdftoppm no encontrado");
            return null;
        }

        // Crear directorio temporal propio
        $ocrTempDir = storage_path('app/ocr_temp/' . uniqid('ocr_'));
        if (!is_dir($ocrTempDir)) {
            mkdir($ocrTempDir, 0755, true);
        }

        $convertCmd = sprintf(
            '"%s" -png -r 150 "%s" "%s/page"',
            $pdftoppm,
            $pdfPath,
            $ocrTempDir
        );

        Log::info("OCR: Intentando con Poppler (pdftoppm)...");

        $output = [];
        $returnCode = 0;
        exec($convertCmd . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning("OCR: Poppler falló", ['output' => $output]);
            $this->safeRemoveDir($ocrTempDir);
            return null;
        }

        $text = $this->processPdfPagesFromDir($ocrTempDir);
        $this->safeRemoveDir($ocrTempDir);
        
        return $text;
    }

    /**
     * Limpia el texto extraído
     */
    private function cleanText(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Extrae datos estructurados del texto OCR
     */
    public function extractStructuredData(string $text): array
    {
        return [
            'numeros_identificacion' => $this->extractNumbers($text),
            'fechas' => $this->extractDates($text),
            'correos' => $this->extractEmails($text),
            'telefonos' => $this->extractPhones($text),
            'direcciones' => $this->extractAddresses($text),
            'codigos' => $this->extractCodes($text),
        ];
    }

    private function extractNumbers(string $text): array
    {
        $patterns = [
            'CC' => '/\bCC[:\s]*(\d{6,12})\b/i',
            'NIT' => '/\bNIT[:\s]*(\d{6,12}(?:\-\d)?)\b/i',
            'RC' => '/\bRC[:\s]*(\d{6,12})\b/i',
            'CE' => '/\bCE[:\s]*([A-Z0-9]{6,12})\b/i',
            'PASAPORTE' => '/\bPASAPORTE[:\s]*([A-Z0-9]{6,12})\b/i',
            'TI' => '/\bTI[:\s]*(\d{6,12})\b/i',
        ];

        return $this->extractMatches($patterns, $text);
    }

    private function extractDates(string $text): array
    {
        $patterns = [
            'DD/MM/YYYY' => '/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/',
            'YYYY-MM-DD' => '/\b(\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2})\b/',
            'Mes DD, YYYY' => '/\b([A-Za-z]+ \d{1,2},? \d{4})\b/',
        ];

        return $this->extractMatches($patterns, $text);
    }

    private function extractEmails(string $text): array
    {
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text, $matches);

        return array_unique($matches[0] ?? []);
    }

    private function extractPhones(string $text): array
    {
        $patterns = [
            'Fijo Colombia' => '/\b(\d{3}[\s\.\-]?\d{3}[\s\.\-]?\d{4})\b/',
            'Celular Colombia' => '/\b(3\d{2}[\s\.\-]?\d{3}[\s\.\-]?\d{4})\b/',
            'Con prefijo' => '/\b(\+\d{1,3}[\s\.\-]?\d{1,14})\b/',
        ];

        return $this->extractMatches($patterns, $text);
    }

    private function extractAddresses(string $text): array
    {
        $pattern = '/\b(Calle|Cra|Carr|Av|Avenida|Diagonal|Cl|Cr|Kr|#|No|Nro)\s*\d+[A-Z]?[\s\,\.\-#\d]*[A-Z]?[A-Z]?\b/i';
        preg_match_all($pattern, $text, $matches);

        return array_unique($matches[0] ?? []);
    }

    private function extractCodes(string $text): array
    {
        $patterns = [
            'Factura' => '/\b[Ff]actura[:\s]*(\d+)\b/',
            'Contrato' => '/\b[Cc]ontrato[:\s]*(\d+)\b/',
            'Guía' => '/\b[Gg]u(?:í|i)a[:\s]*([A-Z0-9\-]+)\b/',
            'Orden' => '/\b[Oo]rden[:\s]*(\d+)\b/',
            'Radicado' => '/\b[Rr]adicado[:\s]*(\d+)\b/',
        ];

        return $this->extractMatches($patterns, $text);
    }

    private function extractMatches(array $patterns, string $text): array
    {
        $results = [];

        foreach ($patterns as $key => $pattern) {
            preg_match_all($pattern, $text, $matches);
            if (!empty($matches[1])) {
                $results[$key] = array_unique($matches[1]);
            }
        }

        return $results;
    }

    /**
     * Verifica si Tesseract está disponible
     */
    public function isAvailable(): bool
    {
        $tesseract = config('services.tesseract.path', 'tesseract');
        $tesseract = str_replace('"', '', $tesseract);
        $output = [];
        exec("\"{$tesseract}\" --version 2>&1", $output, $returnCode);

        return $returnCode === 0;
    }
}
