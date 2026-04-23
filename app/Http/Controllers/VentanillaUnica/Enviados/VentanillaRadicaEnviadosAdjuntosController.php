<?php

namespace App\Http\Controllers\VentanillaUnica\Enviados;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\Enviados\UploadArchivosAdjuntosEnviadoRequest;
use App\Helpers\ArchivoHelper;
use App\Helpers\FileMetadataHelper;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosArchivos;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VentanillaRadicaEnviadosAdjuntosController extends Controller
{
    use ApiResponseTrait;

    private const DISK = 'radicados_enviados';
    private const PERM = 'Radicar -> Cores. Enviada -> ';

    public function __construct()
    {
        $this->middleware('can:' . self::PERM . 'Subir adjuntos')->only(['subirArchivosAdjuntos']);
        $this->middleware('can:' . self::PERM . 'Eliminar adjuntos')->only(['eliminarArchivoAdjunto']);
        $this->middleware('can:' . self::PERM . 'Mostrar')->only(['listarArchivosAdjuntos', 'descargarArchivoAdjunto']);
    }

    public function subirArchivosAdjuntos($id, UploadArchivosAdjuntosEnviadoRequest $request)
    {
        try {
            $radicado = VentanillaRadicaEnviados::find($id);
            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $archivos = $request->file('archivos');
            if (!is_array($archivos)) {
                $archivos = $request->hasFile('archivos') ? [$archivos] : [];
            }
            if (empty($archivos)) {
                return $this->errorResponse('No se encontraron archivos válidos en la solicitud', null, 400);
            }

            $archivosSubidos = [];
            $usuario = Auth::user();

            foreach ($archivos as $archivo) {
                $tempRequest = new \Illuminate\Http\Request();
                $tempRequest->files->set('archivo', $archivo);

                $uploadData = ArchivoHelper::guardarArchivoConHash($tempRequest, 'archivo', self::DISK);

                if (!$uploadData) continue;

                $rutaArchivo = $uploadData['path'];
                $hashSha256 = $uploadData['hash'];

                $archivoAdicional = VentanillaRadicaEnviadosArchivos::create([
                    'radica_enviado_id' => $radicado->id,
                    'subido_por' => $usuario?->id,
                    'archivo' => $rutaArchivo,
                    'nom_origi' => $archivo->getClientOriginalName(),
                    'archivo_peso' => $archivo->getSize(),
                    'hash_sha256' => $hashSha256,
                ]);

                FileMetadataHelper::crearMetadataArchivoAdjuntoEnviados($archivoAdicional);

                $nombreUsuario = $usuario ? trim($usuario->nombres . ' ' . $usuario->apellidos) : 'No se registró usuario';
                $fileUrl = ArchivoHelper::obtenerUrl($rutaArchivo, self::DISK);

                $archivosSubidos[] = [
                    'id' => $archivoAdicional->id,
                    'path' => $rutaArchivo,
                    'subido_por' => $nombreUsuario,
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
            $radicado = VentanillaRadicaEnviados::with('archivos.usuarioSubio')->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $archivos = $radicado->archivos->map(fn ($archivo) => $archivo->getInfoArchivo(true) ?? [
                'id' => $archivo->id,
                'nombre' => basename($archivo->archivo),
                'ruta' => $archivo->archivo,
                'url' => $archivo->getArchivoUrl(),
                'fecha_subida' => $archivo->created_at,
            ])->values();

            return $this->successResponse($archivos, 'Archivos adicionales obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener archivos adicionales', $e->getMessage(), 500);
        }
    }

    public function descargarArchivoAdjunto($id, $archivoId)
    {
        try {
            $archivo = VentanillaRadicaEnviadosArchivos::where('radica_enviado_id', $id)
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
            $archivo = VentanillaRadicaEnviadosArchivos::where('radica_enviado_id', $id)
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