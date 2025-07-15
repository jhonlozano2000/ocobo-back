<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchivoHelper
{
    /**
     * Guarda un archivo en el disco especificado y elimina el archivo actual si existe.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $campo del formulario que contiene el archivo
     * @param string $disk el disco donde se guardará el archivo
     * @param string|null $archivoActual el nombre del archivo actual a eliminar, si existe
     * @return string|null
     */
    public static function guardarArchivo($request, $campo, $disk, $archivoActual = null)
    {
        if ($request->hasFile($campo)) {
            $file = $request->file($campo);

            if ($archivoActual && Storage::disk($disk)->exists($archivoActual)) {
                Storage::disk($disk)->delete($archivoActual);
            }

            $nombreArchivo = Str::random(50) . '.' . $file->extension();
            return $file->storeAs('', $nombreArchivo, $disk);
        }

        return $archivoActual;
    }

    /**
     * Elimina un archivo del disco especificado.
     *
     * @param string $path la ruta del archivo a eliminar
     * @param string $disk el disco donde se encuentra el archivo
     * @return void
     */
    public static function eliminarArchivo($path, $disk)
    {
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * Obtiene la URL de un archivo almacenado en el disco especificado.
     *
     * @param string $path la ruta del archivo
     * @param string $disk el disco donde se encuentra el archivo
     * @return string|null
     */
    public static function obtenerUrl($path, $disk)
    {
        return $path ? Storage::disk($disk)->url($path) : null;
    }

    /**
     * Guarda múltiples archivos en el disco especificado.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $campo del formulario que contiene los archivos
     * @param string $disk el disco donde se guardarán los archivos
     * @return array
     */
    public static function guardarMultiples($request, $campo, $disk)
    {
        $rutas = [];

        if ($request->hasFile($campo)) {
            foreach ($request->file($campo) as $file) {
                if ($file->isValid()) {
                    $nombreArchivo = Str::random(50) . '.' . $file->extension();
                    $ruta = $file->storeAs('', $nombreArchivo, $disk);
                    $rutas[] = $ruta;
                }
            }
        }

        return $rutas;
    }

    /**
     * Elimina múltiples archivos del disco especificado.
     *
     * @param array $paths las rutas de los archivos a eliminar
     * @param string $disk el disco donde se encuentran los archivos
     * @return void
     */
    public static function eliminarMultiples(array $paths, $disk)
    {
        foreach ($paths as $path) {
            if ($path && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }
}
