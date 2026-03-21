<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Helpers\ArchivoHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\VentanillaRadicaInternoArchivos;
use App\Models\VentanillaUnica\VentanillaRadicaInternoArchivosEliminados;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaInternoArchivosController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request La solicitud HTTP validada
     * @return JsonResponse Respuesta JSON con el archivo creado
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Archivo creado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "subido_por": 1,
     *     "nombre_archivo": "archivo1.pdf",
     *     "ruta_archivo": "https://example.com/archivo1.pdf",
     *     "tipo_archivo": "application/pdf",
     *     "tamano_archivo": "100KB",
     *     "extension_archivo": "pdf",
     *     "created_at": "2024-01-01T10:00:00.000000Z",
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "radica_interno_id": ["El radicado interno es obligatorio."],
     *     "subido_por": ["El usuario que subió el archivo es obligatorio."],
     *     "nombre_archivo": ["El nombre del archivo es obligatorio."],
     *     "ruta_archivo": ["La ruta del archivo es obligatoria."],
     *     "tipo_archivo": ["El tipo de archivo es obligatorio."],
     *     "tamano_archivo": ["El tamaño del archivo es obligatorio."],
     *     "extension_archivo": ["La extensión del archivo es obligatoria."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear el archivo",
     *   "error": "Error message"
     * }
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $archivo = VentanillaRadicaInternoArchivos::create($request->validated());

            DB::commit();

            return $this->successResponse($archivo, 'Archivo creado exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el archivo', $e->getMessage(), 500);
        }
    }

    /**
     * Sube archivos adjuntos a la radicación interna.
     *
     * @param int $id ID de la radicación interna
     * @param Request $request La solicitud HTTP validada
     * @return JsonResponse Respuesta JSON con los archivos subidos
     *
     * @urlParam id integer required El ID de la radicación interna. Example: 1
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Archivos subidos exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "path": "https://example.com/archivo1.pdf",
     *       "subido_por": "Juan Pérez",
     *       "file_size": 100000,
     *       "file_type": "application/pdf",
     *       "file_url": "https://example.com/archivo1.pdf"
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Radicación interna no encontrada"
     * }
     *
     * @response 400 {
     *   "status": false,
     *   "message": "No se encontraron archivos válidos en la solicitud"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al subir los archivos",
     *   "error": "Error message"
     * }
     */
    public function subirArchivosAdjuntos($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaInterno::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicación interna no encontrada', null, 404);
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
                // 1. Crear un request vacío
                $tempRequest = new \Illuminate\Http\Request();

                // 2. Inyectar el archivo correctamente en la bolsa de archivos (files)
                $tempRequest->files->set('archivo', $archivo);

                // Usar ArchivoHelper para guardar cada archivo
                $rutaArchivo = ArchivoHelper::guardarArchivo(
                    $tempRequest,
                    'archivo',
                    'radicados_recibidos'
                );

                // Guardar quién subió el archivo (Auth::user() retorna null si no está autenticado)
                $usuario = Auth::user();

                // Crear registro en la tabla de archivos adicionales
                $archivoAdicional = VentanillaRadicaInternoArchivos::create([
                    'radica_interno_id' => $radicado->id,
                    'subido_por' => $usuario->id,
                    'nombre_archivo' => $archivo->getClientOriginalName(),
                    'ruta_archivo' => $rutaArchivo,
                    'tipo_archivo' => $archivo->getMimeType(),
                    'tamano_archivo' => $archivo->getSize(),
                    'extension_archivo' => $archivo->getClientOriginalExtension(),
                ]);

                // Cachear nombre completo del usuario si existe (optimización)
                $nombreUsuario = $usuario
                    ? trim($usuario->nombres . ' ' . $usuario->apellidos)
                    : 'No se registró usuario';

                $fileUrl = ArchivoHelper::obtenerUrl($rutaArchivo, 'radicados_recibidos');

                $archivosSubidos[] = [
                    'id' => $archivoAdicional->id,
                    'path' => $rutaArchivo,
                    'subido_por' => $nombreUsuario,
                    'file_size' => $archivo->getSize(),
                    'file_type' => $archivo->getMimeType(),
                    'file_url' => $fileUrl
                ];
            }

            DB::commit();

            return $this->successResponse($archivosSubidos, 'Archivos adicionales subidos exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al subir archivos adicionales', $e->getMessage(), 500);
        }
    }

    /**
     * Descarga un archivo específico por su ID.
     *
     * @param int $id ID del archivo
     * @return JsonResponse Respuesta JSON con el archivo descargado
     *
     * @urlParam id integer required El ID del archivo. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Archivo descargado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "subido_por": 1,
     *     "nombre_archivo": "archivo1.pdf",
     *     "ruta_archivo": "https://example.com/archivo1.pdf",
     *     "tipo_archivo": "application/pdf",
     *     "tamano_archivo": "100KB",
     *     "extension_archivo": "pdf",
     *     "created_at": "2024-01-01T10:00:00.000000Z",
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
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
     *   "message": "Error al descargar el archivo",
     *   "error": "Error message"
     */
    public function download($id)
    {
        try {
            $archivo = VentanillaRadicaInternoArchivos::find($id);

            if (!$archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $fileUrl = ArchivoHelper::obtenerUrl($archivo->ruta_archivo, 'ventanilla_radica_interno_archivos');
            if (!$fileUrl) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            return \Storage::disk('ventanilla_radica_interno_archivos')->download($archivo->ruta_archivo);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar el archivo', $e->getMessage(), 500);
        }
    }


    /**
     * Obtiene un archivo específico por su ID.
     *
     * @param int $id ID del archivo
     * @return JsonResponse Respuesta JSON con el archivo
     *
     * @urlParam id integer required El ID del archivo. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Archivo encontrado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "subido_por": 1,
     *     "nombre_archivo": "archivo1.pdf",
     *     "ruta_archivo": "https://example.com/archivo1.pdf",
     *     "tipo_archivo": "application/pdf",
     *     "tamano_archivo": "100KB",
     *     "extension_archivo": "pdf",
     *     "created_at": "2024-01-01T10:00:00.000000Z",
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
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
     *   "message": "Error al obtener el archivo",
     *   "error": "Error message"
     */
    public function show($id)
    {
        try {
            $archivo = VentanillaRadicaInternoArchivos::where('radica_interno_id', $id)->get();

            if (!$archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            return $this->successResponse($archivo, 'Archivo encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el archivo', $e->getMessage(), 500);
        }
    }

    /**
     * Edita un archivo específico por su ID.
     *
     * @param int $id ID del archivo
     * @param Request $request La solicitud HTTP validada
     * @return JsonResponse Respuesta JSON con el archivo editado
     *
     * @urlParam id integer required El ID del archivo. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Archivo editado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "subido_por": 1,
     *     "nombre_archivo": "archivo1.pdf",
     *     "ruta_archivo": "https://example.com/archivo1.pdf",
     *     "tipo_archivo": "application/pdf",
     *     "tamano_archivo": "100KB",
     *     "extension_archivo": "pdf",
     *     "created_at": "2024-01-01T10:00:00.000000Z",
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
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
     *   "message": "Error al editar el archivo",
     *   "error": "Error message"
     * }
     */
    public function update($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $archivo = VentanillaRadicaInternoArchivos::find($id);

            if (!$archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $archivo->update($request->validated());

            DB::commit();

            return $this->successResponse($archivo, 'Archivo editado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al editar el archivo', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un archivo específico por su ID.
     *
     * @param int $id ID del archivo
     * @return JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam id integer required El ID del archivo. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Archivo eliminado exitosamente"
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
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $archivo = VentanillaRadicaInternoArchivos::find($id);

            if (!$archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            ArchivoHelper::eliminarArchivo($archivo->ruta_archivo, 'ventanilla_radica_interno_archivos');

            $usuario = Auth::user();
            VentanillaRadicaInternoArchivosEliminados::create([
                'radica_interno_id' => $archivo->radica_interno_id,
                'archivo' => $archivo->ruta_archivo,
                'deleted_by' => $usuario ? $usuario->id : null,
                'deleted_at' => now(),
            ]);

            $archivo->delete();

            DB::commit();

            return $this->successResponse(null, 'Archivo eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el archivo', $e->getMessage(), 500);
        }
    }
}
