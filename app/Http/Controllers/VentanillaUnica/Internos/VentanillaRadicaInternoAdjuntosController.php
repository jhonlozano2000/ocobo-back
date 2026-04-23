<?php

namespace App\Http\Controllers\VentanillaUnica\Internos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\Internos\UploadArchivosAdjuntosInternoRequest;
use App\Helpers\ArchivoHelper;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoArchivos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VentanillaRadicaInternoAdjuntosController extends Controller
{
    use ApiResponseTrait;

    private const DISK = 'ventanilla_radica_interno_archivos';
    private const PERM = 'Radicar -> Cores. Interno -> ';

    public function __construct()
    {
        $this->middleware('can:' . self::PERM . 'Subir adjuntos')->only(['subirArchivosAdjuntos']);
        $this->middleware('can:' . self::PERM . 'Eliminar adjuntos')->only(['eliminarArchivoAdjunto']);
        $this->middleware('can:' . self::PERM . 'Mostrar')->only(['listarArchivosAdjuntos', 'descargarArchivoAdjunto']);
    }

    public function subirArchivosAdjuntos($id, UploadArchivosAdjuntosInternoRequest $request)
    {
        try {
            $radicado = VentanillaRadicaInterno::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $archivos = $request->file('archivos');

            if (!$archivos || (!is_array($archivos) && !$request->hasFile('archivos'))) {
                return $this->errorResponse('No se encontraron archivos para subir', null, 400);
            }

            if (!is_array($archivos)) {
                $archivos = [$archivos];
            }

            $archivosSubidos = [];
            $usuario = Auth::user();

            foreach ($archivos as $archivo) {
                $tempRequest = new Request();
                $tempRequest->files->set('archivo', $archivo);

                $uploadData = ArchivoHelper::guardarArchivoConHash($tempRequest, 'archivo', self::DISK);

                if (!$uploadData) {
                    continue;
                }

                $rutaArchivo = $uploadData['path'];
                $hashSha256 = $uploadData['hash'];

                $archivoAdicional = VentanillaRadicaInternoArchivos::create([
                    'radica_interno_id' => $radicado->id,
                    'subido_por' => $usuario?->id,
                    'archivo' => $rutaArchivo,
                    'nom_origi' => $archivo->getClientOriginalName(),
                    'archivo_peso' => $archivo->getSize(),
                    'hash_sha256' => $hashSha256,
                ]);

                $fileUrl = ArchivoHelper::obtenerUrl($rutaArchivo, self::DISK);

                $archivosSubidos[] = [
                    'id' => $archivoAdicional->id,
                    'path' => $rutaArchivo,
                    'subido_por' => $usuario ? trim($usuario->nombres . ' ' . $usuario->apellidos) : 'No se registró usuario',
                    'file_size' => $archivo->getSize(),
                    'file_type' => $archivo->getMimeType(),
                    'file_url' => $fileUrl,
                ];
            }

            return $this->successResponse($archivosSubidos, 'Archivos adicionales subidos exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al subir archivos adicionales', $e->getMessage(), 500);
        }
    }

    public function listarArchivosAdjuntos($id)
    {
        try {
            $radicado = VentanillaRadicaInterno::with('archivos.usuarioSubio')->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $archivos = $radicado->archivos->map(fn ($archivo) => [
                'id' => $archivo->id,
                'nombre' => $archivo->nom_origi ?? basename($archivo->archivo),
                'ruta' => $archivo->archivo,
                'url' => ArchivoHelper::obtenerUrl($archivo->archivo, self::DISK),
                'fecha_subida' => $archivo->created_at,
                'subido_por' => $archivo->usuarioSubio
                    ? trim($archivo->usuarioSubio->nombres . ' ' . $archivo->usuarioSubio->apellidos)
                    : 'Usuario no identificado',
            ])->values();

            return $this->successResponse($archivos, 'Archivos adicionales obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener archivos adicionales', $e->getMessage(), 500);
        }
    }

    public function descargarArchivoAdjunto($id, $archivoId)
    {
        try {
            $archivo = VentanillaRadicaInternoArchivos::where('radica_interno_id', $id)
                ->where('id', $archivoId)
                ->first();

            if (!$archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            if (!ArchivoHelper::obtenerUrl($archivo->archivo, self::DISK)) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            return Storage::disk(self::DISK)->download($archivo->archivo);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar el archivo', $e->getMessage(), 500);
        }
    }

    public function eliminarArchivoAdjunto($id, $archivoId)
    {
        try {
            $archivo = VentanillaRadicaInternoArchivos::where('radica_interno_id', $id)
                ->where('id', $archivoId)
                ->first();

            if (!$archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $rutaArchivo = $archivo->archivo;
            ArchivoHelper::eliminarArchivo($rutaArchivo, self::DISK);

            $archivo->delete();

            return $this->successResponse(null, 'Archivo adicional eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el archivo adicional', $e->getMessage(), 500);
        }
    }
}