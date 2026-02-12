<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\UploadArchivoRequest;
use App\Http\Requests\Ventanilla\UploadArchivosAdjuntosRequest;
use App\Helpers\ArchivoHelper;
use App\Models\VentanillaUnica\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\VentanillaRadicaEnviadosArchivos;
use App\Models\VentanillaUnica\VentanillaRadicaEnviadosArchivoEliminado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VentanillaRadicaEnviadosArchivosController extends Controller
{
    use ApiResponseTrait;

    private const DISK = 'radicados_enviados';
    private const PERM = 'Radicar -> Cores. Enviada -> ';

    public function __construct()
    {
        $this->middleware('can:' . self::PERM . 'Subir digital')->only(['upload']);
        $this->middleware('can:' . self::PERM . 'Subir adjuntos')->only(['subirArchivosAdjuntos']);
        $this->middleware('can:' . self::PERM . 'Eliminar digital')->only(['deleteFile']);
        $this->middleware('can:' . self::PERM . 'Eliminar adjuntos')->only(['eliminarArchivoAdjunto']);
        $this->middleware('can:' . self::PERM . 'Mostrar')->only(['listarArchivosAdjuntos', 'descargarArchivoAdjunto', 'getFileInfo', 'download', 'historialEliminaciones']);
    }

    public function upload($id, UploadArchivoRequest $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $archivo = $request->file('archivo_digital');
            $archivoActual = $radicado->archivo_digital;

            $nuevoArchivo = ArchivoHelper::guardarArchivo($request, 'archivo_digital', self::DISK, $archivoActual);

            $usuario = Auth::user();
            $radicado->update([
                'archivo_digital' => $nuevoArchivo,
                'subido_por' => $usuario?->id,
            ]);

            DB::commit();

            $nombreUsuario = $usuario ? trim($usuario->nombres . ' ' . $usuario->apellidos) : 'No se registró usuario';
            $fileUrl = ArchivoHelper::obtenerUrl($nuevoArchivo, self::DISK);

            return $this->successResponse([
                'path' => $nuevoArchivo,
                'uploaded_by' => $nombreUsuario,
                'file_size' => $archivo->getSize(),
                'file_type' => $archivo->getMimeType(),
                'file_url' => $fileUrl,
            ], 'Archivo subido exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al subir el archivo', $e->getMessage(), 500);
        }
    }

    public function download($id)
    {
        try {
            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            if (!ArchivoHelper::obtenerUrl($radicado->archivo_digital, self::DISK)) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            return Storage::disk(self::DISK)->download($radicado->archivo_digital);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar el archivo', $e->getMessage(), 500);
        }
    }

    public function deleteFile($id)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $archivoEliminado = $radicado->archivo_digital;
            ArchivoHelper::eliminarArchivo($archivoEliminado, self::DISK);

            $usuario = Auth::user();
            VentanillaRadicaEnviadosArchivoEliminado::create([
                'radica_enviado_id' => $radicado->id,
                'archivo' => $archivoEliminado,
                'deleted_by' => $usuario?->id,
                'deleted_at' => now(),
            ]);

            $radicado->update(['archivo_digital' => null, 'subido_por' => null]);
            DB::commit();

            return $this->successResponse([
                'deleted_by' => $usuario ? trim($usuario->nombres . ' ' . $usuario->apellidos) : 'Usuario no identificado',
                'deleted_at' => now(),
            ], 'Archivo eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el archivo', $e->getMessage(), 500);
        }
    }

    public function getFileInfo($id)
    {
        try {
            $radicado = VentanillaRadicaEnviados::with('usuarioSubio')->find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            if (!ArchivoHelper::obtenerUrl($radicado->archivo_digital, self::DISK)) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            $fileInfo = [
                'file_name' => basename($radicado->archivo_digital),
                'file_size' => Storage::disk(self::DISK)->size($radicado->archivo_digital),
                'file_type' => Storage::disk(self::DISK)->mimeType($radicado->archivo_digital),
                'uploaded_at' => $radicado->updated_at,
                'uploaded_by' => $radicado->usuarioSubio
                    ? trim($radicado->usuarioSubio->nombres . ' ' . $radicado->usuarioSubio->apellidos)
                    : 'Usuario no identificado',
                'file_url' => ArchivoHelper::obtenerUrl($radicado->archivo_digital, self::DISK),
            ];

            return $this->successResponse($fileInfo, 'Información del archivo obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener información del archivo', $e->getMessage(), 500);
        }
    }

    public function subirArchivosAdjuntos($id, UploadArchivosAdjuntosRequest $request)
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

                $rutaArchivo = ArchivoHelper::guardarArchivo($tempRequest, 'archivo', self::DISK);

                $archivoAdicional = VentanillaRadicaEnviadosArchivos::create([
                    'radica_enviado_id' => $radicado->id,
                    'subido_por' => $usuario?->id,
                    'archivo' => $rutaArchivo,
                ]);

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
            DB::beginTransaction();

            $archivo = VentanillaRadicaEnviadosArchivos::where('radica_enviado_id', $id)
                ->where('id', $archivoId)
                ->first();

            if (!$archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $rutaArchivo = $archivo->archivo;
            ArchivoHelper::eliminarArchivo($rutaArchivo, self::DISK);

            $usuario = Auth::user();
            VentanillaRadicaEnviadosArchivoEliminado::create([
                'radica_enviado_id' => $id,
                'archivo' => $rutaArchivo,
                'deleted_by' => $usuario?->id,
                'deleted_at' => now(),
            ]);

            $archivo->delete();

            DB::commit();

            return $this->successResponse(null, 'Archivo adicional eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el archivo adicional', $e->getMessage(), 500);
        }
    }

    public function historialEliminaciones($id)
    {
        try {
            $radicado = VentanillaRadicaEnviados::find($id);
            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $historial = VentanillaRadicaEnviadosArchivoEliminado::where('radica_enviado_id', $id)
                ->with('usuario:id,nombres,apellidos')
                ->orderBy('deleted_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de eliminaciones obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial', $e->getMessage(), 500);
        }
    }
}
