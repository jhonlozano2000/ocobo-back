<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchivoHelper
{
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
            return null;
        }

        $mime = $file->getMimeType();
        $contenido = $file->getContent();

        // Si es un PDF, inyectamos los metadatos de contexto (ISO 27001 - Integridad)
        if ($mime === 'application/pdf' && !empty($metadatos)) {
            $contenido = self::inyectarMetadatosPDF($file->getRealPath(), $metadatos);
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
