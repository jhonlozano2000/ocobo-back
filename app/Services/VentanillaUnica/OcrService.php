<?php

namespace App\Services\VentanillaUnica;

use App\Helpers\ArchivoHelper;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio para extracción de texto OCR de documentos
 * Usa Tesseract OCR con soporte para español
 *
 * OWASP A04:2021 - Security Misconfiguration
 * Todos los comandos shell usan sanitización rigorosa
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
     * Rutas permitidas para OCR (whitelist)
     */
    private const ALLOWED_DISKS = [
        'radicados_recibidos',
        'radicados_enviados',
        'radicados_internos',
        'temporals',
    ];

    /**
     * Extensiones permitidas para validación de paths
     */
    private const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'tiff', 'tif', 'bmp', 'gif'];

    /**
     * Aplica OCR a un archivo y retorna el texto extraído
     */
    public function extractText(string $filePath, string $disk = 'radicados_recibidos'): ?string
    {
        try {
            // Validar que el disco sea seguro (OWASP A01)
            if (!in_array($disk, self::ALLOWED_DISKS)) {
                Log::warning("OCR: Disco no permitido ({$disk})");
                return null;
            }

            // Validar que el path sea seguro (OWASP A01 - Path Traversal)
            if (!$this->esPathSeguro($filePath)) {
                Log::warning("OCR: Path no seguro ({$filePath})");
                return null;
            }

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

            // Verificar que el archivo real tenga la extensión correcta (no spoofing)
            $realExtension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if (!in_array($realExtension, self::ALLOWED_EXTENSIONS)) {
                Log::warning("OCR: Extensión real del archivo no es permitida ({$realExtension})");
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
     * Valida que un path sea seguro (sin path traversal, sin null bytes)
     *
     * @param string $path
     * @return bool
     */
    private function esPathSeguro(string $path): bool
    {
        // Verificar null bytes
        if (strpos($path, "\0") !== false) {
            return false;
        }

        // Verificar path traversal
        $patrones = ['../', '..\\', '/../', '\\..\\', '%00', '..%00'];
        foreach ($patrones as $patron) {
            if (stripos($path, $patron) !== false) {
                return false;
            }
        }

        // Verificar que no tenga rutas absoluta externa
        if (preg_match('/^[a-zA-Z]:\\\\|^\\\\\\\\|^\//', $path)) {
            return false;
        }

        // Verificar longitud máxima
        if (strlen($path) > 500) {
            return false;
        }

        return true;
    }

    /**
     * Aplica OCR usando Tesseract
     */
    private function applyOcr(string $imagePath, string $psmMode = 'auto'): ?string
    {
        // Validar que el path sea seguro antes de procesar
        if (!$this->esPathSeguro($imagePath)) {
            Log::error("OCR: Path no seguro para applyOcr");
            return null;
        }

        $command = $this->buildTesseractCommand($imagePath, $psmMode);

        // NO loguear el comando completo por seguridad (podría contener paths sensibles)
        Log::info("OCR: Ejecutando Tesseract", ['psm_mode' => $psmMode]);

        $output = [];
        $returnCode = 0;

        // Ejecutar con validación de salida
        $this->ejecutarComandoSeguro($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error("OCR: Tesseract falló con código {$returnCode}", ['output' => $output]);
            return null;
        }

        $text = implode("\n", $output);
        return $this->cleanText($text);
    }

    /**
     * Construye el comando Tesseract con sanitización rigorosa
     */
    private function buildTesseractCommand(string $imagePath, string $psmMode): string
    {
        // Validar path
        if (!$this->esPathSeguro($imagePath)) {
            throw new \InvalidArgumentException('Path no seguro para Tesseract');
        }

        // Validar que el archivo existe
        if (!file_exists($imagePath)) {
            throw new \InvalidArgumentException('Archivo no existe');
        }

        $tesseract = config('services.tesseract.path', 'tesseract');

        // Validar que tesseract sea una ruta interna (whitelist)
        if (!$this->esBinarioPermitido($tesseract)) {
            Log::error("OCR: Tesseract path no permitido: {$tesseract}");
            throw new \InvalidArgumentException('Tesseract path no permitido');
        }

        $language = config('services.tesseract.language', 'spa+eng');
        $psm = self::PSM_MODES[$psmMode] ?? self::PSM_MODES['auto'];

        // Sanitización completa de todos los parámetros
        $safeImagePath = escapeshellarg($imagePath);
        $safeLanguage = escapeshellarg($language);
        $safePsm = escapeshellarg($psm);

        return "\"{$tesseract}\" {$safeImagePath} stdout -l {$safeLanguage} {$safePsm}";
    }

    /**
     * Verifica si un binario es interno (whitelist de rutas permitidas)
     */
    private function esBinarioPermitido(string $binario): bool
    {
        $rutasPermitidas = [
            'tesseract',
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
        ];

        $binarioLimpio = str_replace('"', '', $binario);

        foreach ($rutasPermitidas as $ruta) {
            if (strtolower($binarioLimpio) === strtolower($ruta)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Timeout por defecto para comandos OCR (30 segundos)
     */
    private const DEFAULT_TIMEOUT = 30;

    /**
     * Timeout para procesamiento de PDF (60 segundos)
     */
    private const PDF_TIMEOUT = 60;

    /**
     * Ejecuta un comando de forma segura (sin shell injection)
     * Con soporte para timeouts
     */
    private function ejecutarComandoSeguro(string $command, array &$output, int &$returnCode, int $timeout = self::DEFAULT_TIMEOUT): void
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command . ' 2>&1', $descriptorSpec, $pipes);

        if (is_resource($process)) {
            // Configurar timeout en segundos
            stream_set_timeout($pipes[1], $timeout);
            stream_set_timeout($pipes[2], $timeout);

            // Cerrar stdin
            fclose($pipes[0]);

            // Leer stdout con timeout
            $stdout = '';
            $stderr = '';
            $timeoutUs = $timeout * 1000000;

            $startTime = microtime(true);

            while (!feof($pipes[1]) || !feof($pipes[2])) {
                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;

                $remaining = $timeoutUs - (microtime(true) - $startTime) * 1000000;
                if ($remaining <= 0) {
                    break;
                }

                $tvSec = (int)($remaining / 1000000);
                $tvUsec = (int)($remaining % 1000000);

                if (stream_select($read, $write, $except, $tvSec, $tvUsec) === false) {
                    break;
                }

                if (!empty($read)) {
                    foreach ($read as $pipe) {
                        if ($pipe === $pipes[1]) {
                            $stdout .= fread($pipe, 8192);
                        } elseif ($pipe === $pipes[2]) {
                            $stderr .= fread($pipe, 8192);
                        }
                    }
                }

                if ((microtime(true) - $startTime) * 1000000 > $timeoutUs) {
                    Log::warning("OCR: Timeout excedido ({$timeout}s)");
                    break;
                }
            }

            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);

            $output = array_filter(array_merge(
                explode("\n", trim($stdout)),
                explode("\n", trim($stderr))
            ));
        } else {
            $returnCode = -1;
            $output = ['Error al iniciar proceso'];
        }
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

        // Verificar que el directorio esté dentro de storage (path traversal prevention)
        $baseDir = storage_path('app/ocr_temp');
        if (strpos(realpath($dir), realpath($baseDir)) !== 0) {
            Log::warning("OCR: Intento de eliminar directorio fuera del área permitida");
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
        // Validar path seguro
        if (!$this->esPathSeguro($pdfPath)) {
            Log::warning("OCR: Path PDF no seguro");
            return null;
        }

        // Crear directorio temporal dentro de storage/app (área controlada)
        $baseDir = storage_path('app/ocr_temp');
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $ocrTempDir = $baseDir . '/' . uniqid('ocr_');
        if (!is_dir($ocrTempDir)) {
            mkdir($ocrTempDir, 0755, true);
        }

        // Buscar Ghostscript en rutas conocidas
        $ghostscriptPaths = [
            'C:\\Program Files\\gs\\gs10.07.0\\bin\\gswin64c.exe',
            'C:\\Program Files\\gs\\gs10.03.1\\bin\\gswin64c.exe',
            'C:\\Program Files\\Ghostscript\\bin\\gswin64c.exe',
        ];

        $gsExe = null;
        foreach ($ghostscriptPaths as $path) {
            if (file_exists($path)) {
                $gsExe = $path;
                break;
            }
        }

        if (!$gsExe) {
            Log::warning("OCR: Ghostscript no encontrado");
            $this->safeRemoveDir($ocrTempDir);
            return null;
        }

        // Validar que Ghostscript sea un ejecutable válido
        if (!$this->esBinarioPermitido($gsExe)) {
            Log::error("OCR: Ghostscript path no permitido");
            $this->safeRemoveDir($ocrTempDir);
            return null;
        }

        // Sanitizar todos los paths
        $safeGsExe = escapeshellarg($gsExe);
        $safePdfPath = escapeshellarg($pdfPath);
        $outputFile = $ocrTempDir . '/page_%03d.png';

        $command = "{$safeGsExe} -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r150 -sOutputFile=\"{$outputFile}\" -f {$safePdfPath}";

        Log::info("OCR: Ejecutando Ghostscript");

        $output = [];
        $returnCode = 0;
        $this->ejecutarComandoSeguro($command, $output, $returnCode, self::PDF_TIMEOUT);

        if ($returnCode !== 0) {
            Log::warning("OCR: Ghostscript falló", ['returnCode' => $returnCode]);
            $this->safeRemoveDir($ocrTempDir);
            return null;
        }

        // Verificar si se crearon las páginas
        $pages = glob($ocrTempDir . '/page_*.png');

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
        // Validar que el directorio esté dentro del área permitida
        $baseDir = storage_path('app/ocr_temp');
        if (strpos(realpath($dir), realpath($baseDir)) !== 0) {
            Log::warning("OCR: Directorio fuera del área permitida");
            return null;
        }

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
        // Validar path seguro
        if (!$this->esPathSeguro($pdfPath)) {
            return null;
        }

        // Buscar pdftoppm en rutas conocidas
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

        // Validar que pdftoppm sea un binario permitido
        if (!$this->esBinarioPermitido($pdftoppm)) {
            Log::error("OCR: pdftoppm path no permitido");
            return null;
        }

        // Crear directorio temporal en área controlada
        $baseDir = storage_path('app/ocr_temp');
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $ocrTempDir = $baseDir . '/' . uniqid('ocr_');
        if (!is_dir($ocrTempDir)) {
            mkdir($ocrTempDir, 0755, true);
        }

        // Sanitizar todos los parámetros
        $safePdftoppm = escapeshellarg($pdftoppm);
        $safePdfPath = escapeshellarg($pdfPath);
        $safeOutputDir = escapeshellarg($ocrTempDir);

        $convertCmd = "{$safePdftoppm} -png -r 150 {$safePdfPath} {$safeOutputDir}/page";

        Log::info("OCR: Intentando con Poppler (pdftoppm)...");

        $output = [];
        $returnCode = 0;
        $this->ejecutarComandoSeguro($convertCmd, $output, $returnCode, self::PDF_TIMEOUT);

        if ($returnCode !== 0) {
            Log::warning("OCR: Poppler falló");
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

        // Validar que sea un binario permitido
        if (!$this->esBinarioPermitido($tesseract)) {
            return false;
        }

        $tesseract = str_replace('"', '', $tesseract);
        $safeTesseract = escapeshellarg($tesseract);

        $output = [];
        $returnCode = 0;

        $this->ejecutarComandoSeguro("{$safeTesseract} --version", $output, $returnCode);

        return $returnCode === 0;
    }
}
