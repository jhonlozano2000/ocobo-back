<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\UploadArchivoRequest;
use App\Helpers\ArchivoHelper;
use App\Models\Configuracion\ConfigVarias;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use App\Models\VentanillaUnica\VentanillaRadicaReciArchivoEliminado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            // Usar ArchivoHelper para guardar el archivo
            $archivoActual = $radicado->archivo_radica;
            $nuevoArchivo = ArchivoHelper::guardarArchivo($request, 'archivo', 'radicaciones_recibidas', $archivoActual);

            // Guardar quién subió el archivo (si hay usuario autenticado)
            $usuario = Auth::check() ? Auth::user() : null;

            $radicado->update([
                'archivo_radica' => $nuevoArchivo,
                'uploaded_by' => $usuario ? $usuario->id : null,
            ]);

            DB::commit();

            $archivo = $request->file('archivo');
            $fileUrl = ArchivoHelper::obtenerUrl($nuevoArchivo, 'radicaciones_recibidas');

            return $this->successResponse([
                'path' => $nuevoArchivo,
                'uploaded_by' => $usuario ? $usuario->nombres . ' ' . $usuario->apellidos : 'No se registró usuario',
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

            if (!$radicado || !$radicado->archivo_radica) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            // Verificar que el archivo existe usando ArchivoHelper
            $fileUrl = ArchivoHelper::obtenerUrl($radicado->archivo_radica, 'radicaciones_recibidas');
            if (!$fileUrl) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            return \Storage::disk('radicaciones_recibidas')->download($radicado->archivo_radica);
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

            if (!$radicado || !$radicado->archivo_radica) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $archivoEliminado = $radicado->archivo_radica;

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
            $radicado->update(['archivo_radica' => null]);

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

            if (!$radicado || !$radicado->archivo_radica) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            // Usar ArchivoHelper para obtener la URL del archivo
            $fileUrl = ArchivoHelper::obtenerUrl($radicado->archivo_radica, 'radicaciones_recibidas');
            if (!$fileUrl) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            $fileInfo = [
                'file_name' => basename($radicado->archivo_radica),
                'file_size' => \Storage::disk('radicaciones_recibidas')->size($radicado->archivo_radica),
                'file_type' => \Storage::disk('radicaciones_recibidas')->mimeType($radicado->archivo_radica),
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
}
