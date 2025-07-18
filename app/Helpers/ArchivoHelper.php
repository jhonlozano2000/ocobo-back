<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchivoHelper
{
    /**
     * Guarda un archivo en el disco especificado y elimina el archivo actual si existe.
     *
     * @param Request $request
     * @param string $campo
     * @param string $disk
     * @param string|null $archivoActual
     * @return string|null
     */
    public static function guardarArchivo(Request $request, string $campo, string $disk, ?string $archivoActual = null): ?string
    {
        if (!$request->hasFile($campo)) {
            return $archivoActual;
        }

        $file = $request->file($campo);
        if (!$file->isValid()) {
            return $archivoActual;
        }

        $storage = Storage::disk($disk);

        if ($archivoActual && $storage->exists($archivoActual)) {
            $storage->delete($archivoActual);
        }

        $nombreArchivo = Str::random(50) . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('', $nombreArchivo, $disk);
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

        $storage = Storage::disk($disk);
        if ($storage->exists($path)) {
            $storage->delete($path);
        }
    }

    /**
     * Obtiene la URL de un archivo almacenado en el disco especificado.
     *
     * @param string|null $path
     * @param string $disk
     * @return string|null
     */
    public static function obtenerUrl(?string $path, string $disk): ?string
    {
        return $path ? Storage::disk($disk)->url($path) : null;
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
        $storage = Storage::disk($disk);

        foreach ((array)$files as $file) {
            if ($file && $file->isValid()) {
                $nombreArchivo = Str::random(50) . '.' . $file->getClientOriginalExtension();
                $ruta = $file->storeAs('', $nombreArchivo, $disk);
                $rutas[] = $ruta;
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
        $storage = Storage::disk($disk);
        foreach ($paths as $path) {
            if ($path && $storage->exists($path)) {
                $storage->delete($path);
            }
        }
    }
}
