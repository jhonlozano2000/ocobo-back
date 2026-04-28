<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use finfo;

class ArchivoHelper
{
    /**
     * Magic bytes para validación de tipo de archivo (OWASP A03:2021)
     * Formato: [mime_type => [signature_bytes, ...]]
     *
     * @var array
     */
    private static $MAGIC_BYTES = [
        'application/pdf' => [[0x25, 0x50, 0x44, 0x46]], // %PDF
        'image/jpeg' => [
            [0xFF, 0xD8, 0xFF, 0xE0],
            [0xFF, 0xD8, 0xFF, 0xE1],
            [0xFF, 0xD8, 0xFF, 0xE8],
        ],
        'image/png' => [[0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A]],
        'image/gif' => [
            [0x47, 0x49, 0x46, 0x38, 0x37, 0x61], // GIF87a
            [0x47, 0x49, 0x46, 0x38, 0x39, 0x61], // GIF89a
        ],
        'application/msword' => [[0xD0, 0xCF, 0x11, 0xE0, 0xA1, 0xB1, 0x1A, 0xE1]], // OLE2
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [
            [0x50, 0x4B, 0x03, 0x04], // ZIP (DOCX)
        ],
        'application/vnd.ms-excel' => [[0xD0, 0xCF, 0x11, 0xE0, 0xA1, 0xB1, 0x1A, 0xE1]],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [
            [0x50, 0x4B, 0x03, 0x04],
        ],
        'application/vnd.ms-powerpoint' => [[0xD0, 0xCF, 0x11, 0xE0, 0xA1, 0xB1, 0x1A, 0xE1]],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => [
            [0x50, 0x4B, 0x03, 0x04],
        ],
        'application/zip' => [[0x50, 0x4B, 0x03, 0x04]],
        'application/x-zip-compressed' => [[0x50, 0x4B, 0x03, 0x04]],
        'text/plain' => [], // Text files - validar por contenido
        'text/csv' => [], // CSV - validar por contenido
    ];

    /**
     * Tipos MIME permitidos para radicación (AGN Colombia)
     *
     * @var array
     */
    private static $ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    /**
     * Cache estático de instancias de Storage para evitar recrearlas.
     *
     * @var array
     */
    private static $storageCache = [];

    /**
     * Obtiene una instancia de Storage del disco especificado (cacheada).
     *
     * @param string $disk
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    private static function getStorage(string $disk)
    {
        if (!isset(self::$storageCache[$disk])) {
            self::$storageCache[$disk] = Storage::disk($disk);
        }
        return self::$storageCache[$disk];
    }

    /**
     * Valida los magic bytes de un archivo para verificar su tipo real.
     * OWASP A03:2021 - Injection, ISO 27001 A.12.2.1
     *
     * @param \Illuminate\Http\UploadedFile|string $fileOrPath Ruta o archivo uploaded
     * @param string|null $expectedMime Mime type esperado (opcional)
     * @return bool True si los magic bytes coinciden con el tipo esperado
     */
    public static function validarMagicBytes($fileOrPath, ?string $expectedMime = null): bool
    {
        try {
            // Obtener el contenido del archivo
            if ($fileOrPath instanceof \Illuminate\Http\UploadedFile) {
                $path = $fileOrPath->getRealPath();
                $content = file_get_contents($path, false, null, 0, 16);
            } else {
                $content = file_get_contents($fileOrPath, false, null, 0, 16);
            }

            if (empty($content)) {
                return false;
            }

            // Obtener mime type esperado o detectar de magic bytes
            if ($expectedMime) {
                $signatures = self::$MAGIC_BYTES[$expectedMime] ?? [];
                if (empty($signatures)) {
                    // Para tipos sin magic bytes (textos), confiar en el MIME
                    return true;
                }

                // Verificar si alguno de los signatures coincide
                foreach ($signatures as $signature) {
                    if (self::coincideSignature($content, $signature)) {
                        return true;
                    }
                }
                return false;
            }

            // Detectar mime type por magic bytes
            foreach (self::$MAGIC_BYTES as $mime => $signatures) {
                foreach ($signatures as $signature) {
                    if (self::coincideSignature($content, $signature)) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error validando magic bytes', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Compara los primeros bytes del archivo con una firma esperada.
     *
     * @param string $content
     * @param array $signature
     * @return bool
     */
    private static function coincideSignature(string $content, array $signature): bool
    {
        if (empty($signature)) {
            return false;
        }

        for ($i = 0; $i < count($signature); $i++) {
            if (isset($content[$i]) && ord($content[$i]) !== $signature[$i]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Valida que el archivo sea seguro para upload.
     * Combina validación MIME + Magic Bytes + Nombre seguro.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param array|null $allowedMimes Lista de MIME types permitidos (null = usar defaults)
     * @return array ['valido' => bool, 'error' => string|null, 'mime_real' => string|null]
     */
    public static function validarArchivoSeguro($file, ?array $allowedMimes = null): array
    {
        $allowedMimes = $allowedMimes ?? self::$ALLOWED_MIMES;

        // 1. Validar que el archivo existe y es válido
        if (!$file || !$file->isValid()) {
            return ['valido' => false, 'error' => 'Archivo inválido o corrupto', 'mime_real' => null];
        }

        // 2. Obtener MIME type enviado por el cliente
        $clientMime = $file->getMimeType();

        // 3. Verificar que el MIME está en la lista de permitidos
        if (!in_array($clientMime, $allowedMimes)) {
            return ['valido' => false, 'error' => "Tipo MIME no permitido: {$clientMime}", 'mime_real' => null];
        }

        // 4. Validar magic bytes (protección contra spoofing)
        if (!self::validarMagicBytes($file, $clientMime)) {
            Log::warning('Archivo rechazado por validación de magic bytes', [
                'nombre' => $file->getClientOriginalName(),
                'mime_cliente' => $clientMime
            ]);
            return ['valido' => false, 'error' => 'El contenido del archivo no corresponde con su extensión', 'mime_real' => null];
        }

        // 5. Validar nombre de archivo seguro
        $nombreOriginal = $file->getClientOriginalName();
        if (!self::esNombreSeguro($nombreOriginal)) {
            return ['valido' => false, 'error' => 'Nombre de archivo no seguro', 'mime_real' => null];
        }

        // 6. Verificar tamaño máximo (50MB)
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($file->getSize() > $maxSize) {
            return ['valido' => false, 'error' => 'El archivo excede el tamaño máximo permitido (50MB)', 'mime_real' => null];
        }

        // 7. Usar finfo para validación adicional
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getRealPath());
        if (!in_array($realMime, $allowedMimes)) {
            return ['valido' => false, 'error' => "Tipo MIME real no permitido: {$realMime}", 'mime_real' => $realMime];
        }

        return ['valido' => true, 'error' => null, 'mime_real' => $realMime];
    }

    /**
     * Verifica si el nombre de archivo es seguro (sin path traversal).
     *
     * @param string $nombre
     * @return bool
     */
    private static function esNombreSeguro(string $nombre): bool
    {
        // Caracteres peligrosos para path traversal
        $patronesPeligrosos = ['../', '..\\', '/../', '\\..\\', '%00', "\0"];

        foreach ($patronesPeligrosos as $patron) {
            if (stripos($nombre, $patron) !== false) {
                return false;
            }
        }

        // Verificar que no tenga null bytes
        if (strpos($nombre, "\0") !== false) {
            return false;
        }

        // Verificar longitud máxima
        if (strlen($nombre) > 255) {
            return false;
        }

        return true;
    }

    /**
     * Guarda un archivo en el disco especificado y elimina el archivo actual si existe.
     *
     * @param Request $request
     * @param string $campo Nombre del campo del $request en donde se encuentra el archivo
     * @param string $disk Nombre del disco de almacenamiento
     * @param string|null $archivoActual Archivo actual a eliminar (si existe)
     * @return string|null
     */
    public static function guardarArchivo(Request $request, string $campo, string $disk, ?string $archivoActual = null): ?string
    {
        if (!$request->hasFile($campo)) {
            return $archivoActual;
        }

        $file = $request->file($campo);

        // Validación defensiva: si el FormRequest ya validó, esto normalmente no debería fallar
        // pero mantenemos la validación como medida de seguridad
        if (!$file || !$file->isValid()) {
            return $archivoActual;
        }

        $storage = self::getStorage($disk);

        if ($archivoActual && $storage->exists($archivoActual)) {
            $storage->delete($archivoActual);
        }

        $nombreArchivo = Str::random(50) . '.' . $file->getClientOriginalExtension();

        // Usar el contenido del archivo directamente
        $contenido = $file->getContent();
        if (empty($contenido)) {
            // Fallback: intentar leer desde el path real si getContent() no funciona
            $realPath = $file->getRealPath();
            if ($realPath && file_exists($realPath)) {
                $contenido = file_get_contents($realPath);
            } else {
                throw new \Exception('No se pudo leer el contenido del archivo');
            }
        }

        $storage->put($nombreArchivo, $contenido);
        return $nombreArchivo;
    }

    /**
     * Guarda un archivo con hash SHA-256 sin eliminar archivo actual.
     * Retorna path y hash para integridad.
     *
     * @param Request $request
     * @param string $campo
     * @param string $disk
     * @return array|null [path => string, hash => string]
     */
    public static function guardarArchivoConHash(Request $request, string $campo, string $disk): ?array
    {
        if (!$request->hasFile($campo)) {
            return null;
        }

        $file = $request->file($campo);
        if (!$file || !$file->isValid()) {
            return null;
        }

        $storage = self::getStorage($disk);

        $nombreArchivo = Str::random(50) . '.' . $file->getClientOriginalExtension();

        // Obtener contenido del archivo
        $contenido = $file->getContent();
        if (empty($contenido)) {
            // Fallback: usar pathname para archivos en memoria
            $pathname = $file->getPathname();
            if ($pathname && file_exists($pathname)) {
                $contenido = file_get_contents($pathname);
            } else {
                return null;
            }
        }

        // Calcular hash del contenido (no del archivo temporal)
        $hash = hash('sha256', $contenido);

        $storage->put($nombreArchivo, $contenido);

        return [
            'path' => $nombreArchivo,
            'hash' => $hash
        ];
    }

    /**
     * Guarda un archivo en el disco especificado y retorna el path junto con su hash SHA-256 y metadatos tecnicos.
     * Inyecta metadatos internos (Título, Autor, Asunto) en el binario si es un PDF.
     *
     * @param Request $request
     * @param string $campo
     * @param string $disk
     * @param array $metadatos [titulo => string, autor => string, asunto => string]
     * @param string|null $archivoActual
     * @return array|null [path => string, hash => string, mime => string, size => int]
     */
    public static function guardarArchivoConMetadatos(Request $request, string $campo, string $disk, array $metadatos = [], ?string $archivoActual = null): ?array
    {
        if (!$request->hasFile($campo)) {
            return null;
        }

        $file = $request->file($campo);
        if (!$file || !$file->isValid()) {
            Log::warning('guardarArchivoConMetadatos: Archivo inválido', ['campo' => $campo]);
            return null;
        }

        $mime = $file->getMimeType();
        $contenido = $file->getContent();

        // Si es un PDF, inyectamos los metadatos de contexto (ISO 27001 - Integridad)
        if ($mime === 'application/pdf' && !empty($metadatos)) {
            $realPath = $file->getRealPath();
            if (!empty($realPath) && file_exists($realPath)) {
                $contenido = self::inyectarMetadatosPDF($realPath, $metadatos);
            }
        }

        // Generar Hash SHA-256 DESPUÉS de la inyección de metadatos (integridad del binario final)
        $hash = hash('sha256', $contenido);
        $size = strlen($contenido);

        $storage = self::getStorage($disk);

        if ($archivoActual && $storage->exists($archivoActual)) {
            $storage->delete($archivoActual);
        }

        $nombreArchivo = Str::random(50) . '.' . $file->getClientOriginalExtension();

        $storage->put($nombreArchivo, $contenido);

        return [
            'path' => $nombreArchivo,
            'hash' => $hash,
            'mime' => $mime,
            'size' => $size
        ];
    }

    /**
     * Inyecta metadatos internos en un binario PDF usando FPDI.
     *
     * @param string $realPath
     * @param array $metadatos
     * @return string Binario del PDF sellado
     */
    private static function inyectarMetadatosPDF(string $realPath, array $metadatos): string
    {
        try {
            // Verificar que el archivo existe y no está vacío
            if (empty($realPath) || !file_exists($realPath)) {
                Log::warning('inyectarMetadatosPDF: Path vacío o archivo no existe', ['path' => $realPath]);
                return file_get_contents($realPath);
            }

            $pdf = new \setasign\Fpdi\Fpdi();
            
            // Configurar metadatos
            $pdf->SetTitle($metadatos['titulo'] ?? 'Radicado OCOBO', true);
            $pdf->SetAuthor($metadatos['autor'] ?? 'Sistema OCOBO', true);
            $pdf->SetSubject($metadatos['asunto'] ?? 'Gestión Documental', true);
            $pdf->SetKeywords('OCOBO, Colombia, SGDEA', true);
            $pdf->SetCreator('OCOBO - Software de Gestión Documental', true);

            // Importar todas las páginas del original
            $pageCount = $pdf->setSourceFile($realPath);
            for ($n = 1; $n <= $pageCount; $n++) {
                $templateId = $pdf->importPage($n);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            return $pdf->Output('S'); // Retorna el binario como string
        } catch (\Exception $e) {
            // Si falla la inyección (ej: PDF encriptado), retornamos el contenido original
            Log::warning('inyectarMetadatosPDF falló, usando original', [
                'path' => $realPath,
                'error' => $e->getMessage()
            ]);
            return file_get_contents($realPath);
        }
    }

    /**
     * Elimina un archivo del disco especificado.
     *
     * @param string|null $path
     * @param string $disk
     * @return void
     */
    public static function eliminarArchivo(?string $path, string $disk): void
    {
        if (!$path) {
            return;
        }

        $storage = self::getStorage($disk);
        if ($storage->exists($path)) {
            $storage->delete($path);
        }
    }

    /**
     * Obtiene la URL SEGURA de un archivo almacenado en el disco especificado.
     * Genera un enlace hacia DocumentoController en lugar de una URL pública.
     *
     * @param string|null $path
     * @param string $disk
     * @return string|null
     */
    public static function obtenerUrl(?string $path, string $disk): ?string
    {
        if (!$path) {
            return null;
        }

        // Generar URL apuntando al endpoint seguro protegido por Sanctum
        return url('/api/documentos/ver?disk=' . urlencode($disk) . '&path=' . urlencode($path));
    }

    /**
     * Guarda múltiples archivos en el disco especificado y retorna sus paths y hashes.
     *
     * @param Request $request
     * @param string $campo
     * @param string $disk
     * @return array Array de arrays: [['path' => string, 'hash' => string], ...]
     */
    public static function guardarMultiplesConHash(Request $request, string $campo, string $disk): array
    {
        $resultados = [];
        if (!$request->hasFile($campo)) {
            return $resultados;
        }

        $files = $request->file($campo);
        $storage = self::getStorage($disk);

        foreach ((array)$files as $file) {
            if ($file && $file->isValid()) {
                // Generar Hash SHA-256 ANTES de mover el archivo
                $hash = hash_file('sha256', $file->getRealPath());

                $nombreArchivo = Str::random(50) . '.' . $file->getClientOriginalExtension();

                $contenido = $file->getContent();
                if (empty($contenido)) {
                    $realPath = $file->getRealPath();
                    if ($realPath && file_exists($realPath)) {
                        $contenido = file_get_contents($realPath);
                    } else {
                        continue; 
                    }
                }

                $storage->put($nombreArchivo, $contenido);
                
                $resultados[] = [
                    'path' => $nombreArchivo,
                    'hash' => $hash
                ];
            }
        }

        return $resultados;
    }

    /**
     * Elimina múltiples archivos del disco especificado.
     *
     * @param array $paths
     * @param string $disk
     * @return void
     */
    public static function eliminarMultiples(array $paths, string $disk): void
    {
        $storage = self::getStorage($disk);
        foreach ($paths as $path) {
            if ($path && $storage->exists($path)) {
                $storage->delete($path);
            }
        }
    }
}
