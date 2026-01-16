<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\UploadArchivoRequest;
use App\Http\Requests\Ventanilla\UploadArchivosAdjuntosRequest;
use App\Helpers\ArchivoHelper;
use App\Models\Configuracion\ConfigVarias;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use App\Models\VentanillaUnica\VentanillaRadicaReciArchivo;
use App\Models\VentanillaUnica\VentanillaRadicaReciArchivoEliminado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para la gestión de archivos de radicaciones recibidas.
 *
 * Este controlador maneja dos tipos de archivos:
 * 1. Archivo digital principal (campo archivo_digital en ventanilla_radica_reci)
 * 2. Archivos adicionales (tabla ventanilla_radica_reci_archivos)
 *
 * Utiliza ArchivoHelper para la gestión segura de archivos en el disco 'radicaciones_recibidas'.
 *
 * @package App\Http\Controllers\VentanillaUnica
 * @author Sistema OCOBO
 * @version 1.0
 */
class VentanillaRadicaReciArchivosController extends Controller
{
    use ApiResponseTrait;

    /**
     * Sube un archivo asociado a una radicación específica.
     *
     * Este método permite subir archivos a una radicación existente,
     * validando el tipo y tamaño del archivo según la configuración del sistema.
     * Utiliza ArchivoHelper para la gestión segura de archivos.
     *
     * @param int $id ID de la radicación
     * @param UploadArchivoRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con información del archivo subido
     *
     * @urlParam id integer required El ID de la radicación. Example: 1
     * @bodyParam archivo file required Archivo a subir. Example: "documento.pdf"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Archivo subido exitosamente",
     *   "data": {
     *     "path": "radicaciones_recibidas/documento.pdf",
     *     "uploaded_by": "Juan Pérez",
     *     "file_size": 1024000,
     *     "file_type": "application/pdf",
     *     "file_url": "http://example.com/storage/radicaciones_recibidas/documento.pdf"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Radicación no encontrada"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "archivo": ["El archivo es obligatorio."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al subir el archivo",
     *   "error": "Error message"
     * }
     */
    public function upload($id, UploadArchivoRequest $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaReci::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            // Obtener archivo una sola vez (optimización)
            $archivo = $request->file('archivo');
            
            // Usar ArchivoHelper para guardar el archivo
            $archivoActual = $radicado->archivo_digital;
            $nuevoArchivo = ArchivoHelper::guardarArchivo($request, 'archivo', 'radicaciones_recibidas', $archivoActual);

            // Guardar quién subió el archivo (Auth::user() retorna null si no está autenticado)
            $usuario = Auth::user();

            $radicado->update([
                'archivo_digital' => $nuevoArchivo,
                'uploaded_by' => $usuario?->id,
            ]);

            DB::commit();

            // Cachear nombre completo del usuario si existe (optimización)
            $nombreUsuario = $usuario 
                ? trim($usuario->nombres . ' ' . $usuario->apellidos) 
                : 'No se registró usuario';

            $fileUrl = ArchivoHelper::obtenerUrl($nuevoArchivo, 'radicaciones_recibidas');

            return $this->successResponse([
                'path' => $nuevoArchivo,
                'uploaded_by' => $nombreUsuario,
                'file_size' => $archivo->getSize(),
                'file_type' => $archivo->getMimeType(),
                'file_url' => $fileUrl
            ], 'Archivo subido exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al subir el archivo', $e->getMessage(), 500);
        }
    }

    /**
     * Descarga un archivo asociado a una radicación específica.
     *
     * Este método permite descargar archivos asociados a radicaciones,
     * verificando que el archivo exista antes de la descarga.
     *
     * @param int $id ID de la radicación
     * @return \Illuminate\Http\Response Respuesta de descarga del archivo
     *
     * @urlParam id integer required El ID de la radicación. Example: 1
     *
     * @response 200 {
     *   "file": "binary content"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Archivo no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al descargar el archivo",
     *   "error": "Error message"
     * }
     */
    public function download($id)
    {
        try {
            $radicado = VentanillaRadicaReci::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            // Verificar que el archivo existe usando ArchivoHelper
            $fileUrl = ArchivoHelper::obtenerUrl($radicado->archivo_digital, 'radicaciones_recibidas');
            if (!$fileUrl) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            return \Storage::disk('radicaciones_recibidas')->download($radicado->archivo_digital);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar el archivo', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un archivo asociado a una radicación específica.
     *
     * Este método permite eliminar archivos de radicaciones,
     * manteniendo un historial de eliminaciones para auditoría.
     * Utiliza ArchivoHelper para la eliminación segura.
     *
     * @param int $id ID de la radicación
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam id integer required El ID de la radicación. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Archivo eliminado exitosamente",
     *   "data": {
     *     "deleted_by": "Juan Pérez",
     *     "deleted_at": "2024-01-01T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Archivo no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar el archivo",
     *   "error": "Error message"
     * }
     */
    public function deleteFile($id)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaReci::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $archivoEliminado = $radicado->archivo_digital;

            // Usar ArchivoHelper para eliminar el archivo
            ArchivoHelper::eliminarArchivo($archivoEliminado, 'radicaciones_recibidas');

            // Guardar información del usuario que lo eliminó en el historial
            $usuario = Auth::user();
            VentanillaRadicaReciArchivoEliminado::create([
                'radicado_id' => $radicado->id,
                'archivo' => $archivoEliminado,
                'deleted_by' => $usuario ? $usuario->id : null,
                'deleted_at' => now(),
            ]);

            // Limpiar el campo en la tabla principal
            $radicado->update(['archivo_digital' => null]);

            DB::commit();

            return $this->successResponse([
                'deleted_by' => $usuario ? $usuario->nombres . ' ' . $usuario->apellidos : 'Usuario no identificado',
                'deleted_at' => now()
            ], 'Archivo eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el archivo', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el historial de eliminaciones de archivos para una radicación.
     *
     * Este método permite consultar el historial de archivos eliminados
     * de una radicación específica, incluyendo información del usuario
     * que realizó la eliminación.
     *
     * @param int $id ID de la radicación
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el historial
     *
     * @urlParam id integer required El ID de la radicación. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Historial de eliminaciones obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "radicado_id": 1,
     *       "archivo": "radicaciones_recibidas/documento.pdf",
     *       "deleted_at": "2024-01-01T10:00:00.000000Z",
     *       "usuario": {
     *         "id": 1,
     *         "nombres": "Juan",
     *         "apellidos": "Pérez"
     *       }
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Radicación no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el historial",
     *   "error": "Error message"
     * }
     */
    public function historialEliminaciones($id)
    {
        try {
            // Verificar que la radicación existe
            $radicado = VentanillaRadicaReci::find($id);
            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            $historial = VentanillaRadicaReciArchivoEliminado::where('radicado_id', $id)
                ->with('usuario')
                ->orderBy('deleted_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de eliminaciones obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene información detallada de un archivo asociado a una radicación.
     *
     * Este método permite obtener información detallada de un archivo
     * sin descargarlo, incluyendo tamaño, tipo y fecha de subida.
     * Utiliza ArchivoHelper para obtener la URL del archivo.
     *
     * @param int $id ID de la radicación
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con información del archivo
     *
     * @urlParam id integer required El ID de la radicación. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Información del archivo obtenida exitosamente",
     *   "data": {
     *     "file_name": "documento.pdf",
     *     "file_size": 1024000,
     *     "file_type": "application/pdf",
     *     "uploaded_at": "2024-01-01T10:00:00.000000Z",
     *     "uploaded_by": "Juan Pérez",
     *     "file_url": "http://example.com/storage/radicaciones_recibidas/documento.pdf"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Archivo no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener información del archivo",
     *   "error": "Error message"
     * }
     */
    public function getFileInfo($id)
    {
        try {
            $radicado = VentanillaRadicaReci::with('usuarioSubio')->find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            // Usar ArchivoHelper para obtener la URL del archivo
            $fileUrl = ArchivoHelper::obtenerUrl($radicado->archivo_digital, 'radicaciones_recibidas');
            if (!$fileUrl) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            $fileInfo = [
                'file_name' => basename($radicado->archivo_digital),
                'file_size' => \Storage::disk('radicaciones_recibidas')->size($radicado->archivo_digital),
                'file_type' => \Storage::disk('radicaciones_recibidas')->mimeType($radicado->archivo_digital),
                'uploaded_at' => $radicado->updated_at,
                'uploaded_by' => $radicado->usuarioSubio ?
                    $radicado->usuarioSubio->nombres . ' ' . $radicado->usuarioSubio->apellidos :
                    'Usuario no identificado',
                'file_url' => $fileUrl
            ];

            return $this->successResponse($fileInfo, 'Información del archivo obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener información del archivo', $e->getMessage(), 500);
        }
    }

    /**
     * Sube archivos adicionales asociados a una radicación específica.
     *
     * Este método permite subir múltiples archivos adicionales a una radicación existente,
     * almacenándolos en la tabla ventanilla_radica_reci_archivos.
     *
     * @param int $id ID de la radicación
     * @param UploadArchivosAdjuntosRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con información de los archivos subidos
     */
    public function subirArchivosAdjuntos($id, UploadArchivosAdjuntosRequest $request)
    {
        try {
            $radicado = VentanillaRadicaReci::find($id);
            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            $archivosSubidos = [];

            foreach ($request->file('archivos') as $archivo) {
                // Usar ArchivoHelper para guardar cada archivo
                $rutaArchivo = ArchivoHelper::guardarArchivo(
                    new \Illuminate\Http\Request(['archivo' => $archivo]),
                    'archivo',
                    'radicaciones_recibidas'
                );

                // Crear registro en la tabla de archivos adicionales
                $archivoAdicional = VentanillaRadicaReciArchivo::create([
                    'radicado_id' => $radicado->id,
                    'archivo' => $rutaArchivo
                ]);

                $archivosSubidos[] = [
                    'id' => $archivoAdicional->id,
                    'nombre' => basename($rutaArchivo),
                    'ruta' => $rutaArchivo,
                    'url' => ArchivoHelper::obtenerUrl($rutaArchivo, 'radicaciones_recibidas'),
                    'tamaño' => \Storage::disk('radicaciones_recibidas')->size($rutaArchivo),
                    'tipo' => \Storage::disk('radicaciones_recibidas')->mimeType($rutaArchivo)
                ];
            }

            return $this->successResponse(
                'Archivos adicionales subidos exitosamente',
                $archivosSubidos
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al subir archivos adicionales', $e->getMessage(), 500);
        }
    }

    /**
     * Lista todos los archivos adicionales de una radicación.
     *
     * @param int $id ID de la radicación
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarArchivosAdjuntos($id)
    {
        try {
            $radicado = VentanillaRadicaReci::with('archivos')->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            $archivos = $radicado->archivos->map(function ($archivo) {
                return [
                    'id' => $archivo->id,
                    'nombre' => basename($archivo->archivo),
                    'ruta' => $archivo->archivo,
                    'url' => ArchivoHelper::obtenerUrl($archivo->archivo, 'radicaciones_recibidas'),
                    'tamaño' => \Storage::disk('radicaciones_recibidas')->size($archivo->archivo),
                    'tipo' => \Storage::disk('radicaciones_recibidas')->mimeType($archivo->archivo),
                    'fecha_subida' => $archivo->created_at
                ];
            });

            return $this->successResponse(
                'Archivos adicionales obtenidos exitosamente',
                $archivos
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener archivos adicionales', $e->getMessage(), 500);
        }
    }

    /**
     * Descarga un archivo adicional específico.
     *
     * @param int $id ID de la radicación
     * @param int $archivoId ID del archivo adicional
     * @return \Illuminate\Http\Response
     */
    public function descargarArchivoAdjunto($id, $archivoId)
    {
        try {
            $archivo = VentanillaRadicaReciArchivo::where('radicado_id', $id)
                ->where('id', $archivoId)
                ->first();

            if (!$archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            // Verificar que el archivo existe usando ArchivoHelper
            $fileUrl = ArchivoHelper::obtenerUrl($archivo->archivo, 'radicaciones_recibidas');
            if (!$fileUrl) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            return \Storage::disk('radicaciones_recibidas')->download($archivo->archivo);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar el archivo', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un archivo adicional específico.
     *
     * @param int $id ID de la radicación
     * @param int $archivoId ID del archivo adicional
     * @return \Illuminate\Http\JsonResponse
     */
    public function eliminarArchivoAdjunto($id, $archivoId)
    {
        try {
            DB::beginTransaction();

            $archivo = VentanillaRadicaReciArchivo::where('radicado_id', $id)
                ->where('id', $archivoId)
                ->first();

            if (!$archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $rutaArchivo = $archivo->archivo;

            // Usar ArchivoHelper para eliminar el archivo del disco
            ArchivoHelper::eliminarArchivo($rutaArchivo, 'radicaciones_recibidas');

            // Eliminar el registro de la base de datos
            $archivo->delete();

            DB::commit();

            return $this->successResponse('Archivo adicional eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el archivo adicional', $e->getMessage(), 500);
        }
    }
}
