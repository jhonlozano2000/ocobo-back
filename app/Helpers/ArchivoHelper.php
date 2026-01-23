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
     * Obtiene la URL de un archivo almacenado en el disco especificado.
     * Optimizado con cache de instancias de Storage.
     *
     * @param string|null $path
     * @param string $disk
     * @return string|null
     */
    public static function obtenerUrl(?string $path, string $disk): ?string
    {
        return $path ? self::getStorage($disk)->url($path) : null;
    }

    /**
     * Guarda múltiples archivos en el disco especificado.
     *
     * @param Request $request
     * @param string $campo
     * @param string $disk
     * @return array
     */
    public static function guardarMultiples(Request $request, string $campo, string $disk): array
    {
        $rutas = [];
        if (!$request->hasFile($campo)) {
            return $rutas;
        }

        $files = $request->file($campo);
        $storage = self::getStorage($disk);

        foreach ((array)$files as $file) {
            if ($file && $file->isValid()) {
                $nombreArchivo = Str::random(50) . '.' . $file->getClientOriginalExtension();

                // Usar el contenido del archivo directamente
                $contenido = $file->getContent();
                if (empty($contenido)) {
                    // Fallback: intentar leer desde el path real si getContent() no funciona
                    $realPath = $file->getRealPath();
                    if ($realPath && file_exists($realPath)) {
                        $contenido = file_get_contents($realPath);
                    } else {
                        continue; // Saltar este archivo si no se puede leer
                    }
                }

                $storage->put($nombreArchivo, $contenido);
                $rutas[] = $nombreArchivo;
            }
        }

        return $rutas;
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
