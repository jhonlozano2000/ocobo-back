<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoPlantillaDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlantillaDocumentoService
{
    private const DISCO = 'plantillas';

    public function __construct() {}

    public function subirPlantilla(array $data, UploadedFile $archivo): OfiArchivoPlantillaDocumento
    {
        DB::beginTransaction();
        $path = null;
        try {
            $uuid = Str::uuid();
            $extension = $archivo->getClientOriginalExtension();
            $nombreArchivo = "{$uuid}.{$extension}";

            $path = $archivo->storeAs('', $nombreArchivo, self::DISCO);

            $rutaCompleta = storage_path("app/plantillas/{$path}");
            $hash = hash_file('sha256', $rutaCompleta);

            $plantilla = OfiArchivoPlantillaDocumento::create([
                'nombre_original' => $archivo->getClientOriginalName(),
                'nombre_archivo' => $nombreArchivo,
                'ruta_completa' => $path,
                'peso' => round($archivo->getSize() / 1024, 2),
                'extension' => $extension,
                'mime_type' => $archivo->getMimeType(),
                'hash_seguridad' => $hash,
                'version' => $data['version'] ?? '1.0',
                'descripcion' => $data['descripcion'] ?? null,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'categoria_id' => $data['categoria_id'] ?? null,
                'user_crea_id' => auth()->id(),
            ]);

            DB::commit();

            return $plantilla;
        } catch (\Exception $e) {
            DB::rollBack();
            if ($path) {
                Storage::disk(self::DISCO)->delete($path);
            }
            throw $e;
        }
    }

    public function actualizarPlantilla(
        OfiArchivoPlantillaDocumento $plantilla,
        array $data,
        ?UploadedFile $archivo = null
    ): OfiArchivoPlantillaDocumento {
        DB::beginTransaction();
        $oldPath = null;

        try {
            $updateData = [
                'nombre_original' => $data['nombre'] ?? $plantilla->nombre_original,
                'descripcion' => $data['descripcion'] ?? $plantilla->descripcion,
                'fecha_vencimiento' => array_key_exists('fecha_vencimiento', $data)
                    ? $data['fecha_vencimiento']
                    : $plantilla->fecha_vencimiento,
                'user_actualiza_id' => auth()->id(),
            ];

            if (array_key_exists('categoria_id', $data)) {
                $updateData['categoria_id'] = $data['categoria_id'];
            }

            if (isset($data['activo'])) {
                $updateData['activo'] = $data['activo'];
            }

            if ($archivo) {
                $oldPath = $plantilla->ruta_completa;

                $uuid = Str::uuid();
                $extension = $archivo->getClientOriginalExtension();
                $nombreArchivo = "{$uuid}.{$extension}";

                $newPath = $archivo->storeAs('', $nombreArchivo, self::DISCO);

                $rutaCompleta = storage_path("app/plantillas/{$newPath}");
                $hash = hash_file('sha256', $rutaCompleta);

                $versionActual = $plantilla->version;
                $partes = explode('.', $versionActual);
                $patch = (int) ($partes[1] ?? 0) + 1;
                $nuevaVersion = "{$partes[0]}.{$patch}";

                $updateData = array_merge($updateData, [
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'nombre_archivo' => $nombreArchivo,
                    'ruta_completa' => $newPath,
                    'peso' => round($archivo->getSize() / 1024, 2),
                    'extension' => $extension,
                    'mime_type' => $archivo->getMimeType(),
                    'hash_seguridad' => $hash,
                    'version' => $nuevaVersion,
                ]);
            }

            $plantilla->update($updateData);

            if ($oldPath) {
                Storage::disk(self::DISCO)->delete($oldPath);
            }

            DB::commit();

            return $plantilla->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function eliminarPlantilla(OfiArchivoPlantillaDocumento $plantilla): void
    {
        $plantilla->update([
            'activo' => false,
            'user_actualiza_id' => auth()->id(),
        ]);
        $plantilla->delete();
    }

    public function descargarPlantilla(OfiArchivoPlantillaDocumento $plantilla): string
    {
        return storage_path("app/plantillas/{$plantilla->ruta_completa}");
    }

    public function verificarIntegridad(OfiArchivoPlantillaDocumento $plantilla): bool
    {
        return $plantilla->verificarIntegridad();
    }

    public function reemplazarVariables(string $contenido, array $variables): string
    {
        $search = [];
        $replace = [];

        foreach ($variables as $key => $val) {
            $limpia = str_replace(['${', '}'], '', (string) $key);
            $search[] = '${'.$limpia.'}';
            $replace[] = (string) ($val ?? '');
        }

        return str_replace($search, $replace, $contenido);
    }
}
