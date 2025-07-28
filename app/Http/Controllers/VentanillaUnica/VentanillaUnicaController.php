<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\StoreVentanillaUnicaRequest;
use App\Http\Requests\Ventanilla\UpdateVentanillaUnicaRequest;
use App\Http\Requests\Ventanilla\ListVentanillaUnicaRequest;
use App\Http\Requests\Ventanilla\ConfigurarTiposDocumentalesRequest;
use App\Models\VentanillaUnica\VentanillaUnica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentanillaUnicaController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todas las ventanillas de una sede específica.
     *
     * Este método retorna todas las ventanillas registradas en una sede específica.
     * Es útil para interfaces de administración donde se necesita mostrar
     * las ventanillas disponibles en una sede.
     *
     * @param int $sedeId ID de la sede
     * @param ListVentanillaUnicaRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de ventanillas
     *
     * @urlParam sedeId integer required El ID de la sede. Example: 1
     * @queryParam search string Buscar por nombre o descripción. Example: "Principal"
     * @queryParam estado integer Filtrar por estado (0 inactivo, 1 activo). Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de ventanillas obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "sede_id": 1,
     *       "nombre": "Ventanilla Principal",
     *       "descripcion": "Ventanilla principal de la sede",
     *       "numeracion_unificada": true,
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z",
     *       "sede": {
     *         "id": 1,
     *         "nombre": "Sede Principal",
     *         "codigo": "SEDE001"
     *       }
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Sede no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de ventanillas",
     *   "error": "Error message"
     * }
     */
    public function index($sedeId, ListVentanillaUnicaRequest $request)
    {
        try {
            $query = VentanillaUnica::with('sede')->where('sede_id', $sedeId);

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('descripcion', 'like', "%{$search}%");
                });
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            // Ordenar por nombre
            $query->orderBy('nombre', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $ventanillas = $query->paginate($perPage);
            } else {
                $ventanillas = $query->get();
            }

            return $this->successResponse($ventanillas, 'Listado de ventanillas obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de ventanillas', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva ventanilla en una sede específica.
     *
     * Este método permite crear una nueva ventanilla en una sede específica.
     * La ventanilla incluye nombre, descripción y configuración de numeración.
     *
     * @param int $sedeId ID de la sede
     * @param StoreVentanillaUnicaRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la ventanilla creada
     *
     * @urlParam sedeId integer required El ID de la sede. Example: 1
     * @bodyParam nombre string required Nombre de la ventanilla. Example: "Ventanilla Principal"
     * @bodyParam descripcion string Descripción de la ventanilla. Example: "Ventanilla principal de la sede"
     * @bodyParam numeracion_unificada boolean Configuración de numeración unificada. Example: true
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Ventanilla creada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "sede_id": 1,
     *     "nombre": "Ventanilla Principal",
     *     "descripcion": "Ventanilla principal de la sede",
     *     "numeracion_unificada": true,
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "nombre": ["El nombre es obligatorio."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear la ventanilla",
     *   "error": "Error message"
     * }
     */
    public function store($sedeId, StoreVentanillaUnicaRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            $validatedData['sede_id'] = $sedeId;

            // Convertir numeracion_unificada a booleano si se proporciona
            if (isset($validatedData['numeracion_unificada'])) {
                $validatedData['numeracion_unificada'] = filter_var($validatedData['numeracion_unificada'], FILTER_VALIDATE_BOOLEAN);
            }

            $ventanilla = VentanillaUnica::create($validatedData);

            DB::commit();

            return $this->successResponse(
                $ventanilla->load('sede'),
                'Ventanilla creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear la ventanilla', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una ventanilla específica por su ID.
     *
     * Este método permite obtener los detalles de una ventanilla específica.
     * Es útil para mostrar información detallada o para formularios de edición.
     *
     * @param int $sedeId ID de la sede
     * @param int $id ID de la ventanilla
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la ventanilla
     *
     * @urlParam sedeId integer required El ID de la sede. Example: 1
     * @urlParam id integer required El ID de la ventanilla. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Ventanilla encontrada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "sede_id": 1,
     *     "nombre": "Ventanilla Principal",
     *     "descripcion": "Ventanilla principal de la sede",
     *     "numeracion_unificada": true,
     *     "sede": {
     *       "id": 1,
     *       "nombre": "Sede Principal",
     *       "codigo": "SEDE001"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Ventanilla no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la ventanilla",
     *   "error": "Error message"
     * }
     */
    public function show($sedeId, $id)
    {
        try {
            $ventanilla = VentanillaUnica::with('sede')
                ->where('sede_id', $sedeId)
                ->findOrFail($id);

            return $this->successResponse($ventanilla, 'Ventanilla encontrada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la ventanilla', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una ventanilla existente en el sistema.
     *
     * Este método permite modificar los datos de una ventanilla existente,
     * incluyendo conversión automática del campo numeracion_unificada.
     *
     * @param int $sedeId ID de la sede
     * @param int $id ID de la ventanilla
     * @param UpdateVentanillaUnicaRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la ventanilla actualizada
     *
     * @urlParam sedeId integer required El ID de la sede. Example: 1
     * @urlParam id integer required El ID de la ventanilla. Example: 1
     * @bodyParam nombre string Nombre de la ventanilla. Example: "Ventanilla Principal Actualizada"
     * @bodyParam descripcion string Descripción de la ventanilla. Example: "Ventanilla principal actualizada"
     * @bodyParam numeracion_unificada boolean Configuración de numeración unificada. Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Ventanilla actualizada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "sede_id": 1,
     *     "nombre": "Ventanilla Principal Actualizada",
     *     "descripcion": "Ventanilla principal actualizada",
     *     "numeracion_unificada": true,
     *     "updated_at": "2024-01-01T00:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Ventanilla no encontrada"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "nombre": ["El nombre es obligatorio."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la ventanilla",
     *   "error": "Error message"
     * }
     */
    public function update($sedeId, $id, UpdateVentanillaUnicaRequest $request)
    {
        try {
            DB::beginTransaction();

            $ventanilla = VentanillaUnica::where('sede_id', $sedeId)->findOrFail($id);

            $validatedData = $request->validated();

            // Convertir numeracion_unificada a booleano si se proporciona
            if (isset($validatedData['numeracion_unificada'])) {
                $validatedData['numeracion_unificada'] = filter_var($validatedData['numeracion_unificada'], FILTER_VALIDATE_BOOLEAN);
            }

            $ventanilla->update($validatedData);

            DB::commit();

            return $this->successResponse(
                $ventanilla->load('sede'),
                'Ventanilla actualizada exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la ventanilla', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una ventanilla del sistema.
     *
     * Este método permite eliminar una ventanilla específica del sistema.
     * Se recomienda verificar que no tenga dependencias antes de eliminar.
     *
     * @param int $sedeId ID de la sede
     * @param int $id ID de la ventanilla
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam sedeId integer required El ID de la sede. Example: 1
     * @urlParam id integer required El ID de la ventanilla a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Ventanilla eliminada exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Ventanilla no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar la ventanilla",
     *   "error": "Error message"
     * }
     */
    public function destroy($sedeId, $id)
    {
        try {
            DB::beginTransaction();

            $ventanilla = VentanillaUnica::where('sede_id', $sedeId)->findOrFail($id);

            // Verificar si tiene dependencias antes de eliminar
            if ($ventanilla->usuariosPermitidos()->count() > 0) {
                return $this->errorResponse('No se puede eliminar la ventanilla porque tiene usuarios asignados', null, 422);
            }

            if ($ventanilla->tiposDocumentales()->count() > 0) {
                return $this->errorResponse('No se puede eliminar la ventanilla porque tiene tipos documentales configurados', null, 422);
            }

            $ventanilla->delete();

            DB::commit();

            return $this->successResponse(null, 'Ventanilla eliminada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar la ventanilla', $e->getMessage(), 500);
        }
    }

    /**
     * Configura los tipos documentales permitidos en la ventanilla.
     *
     * Este método permite configurar qué tipos documentales están permitidos
     * en una ventanilla específica.
     *
     * @param int $id ID de la ventanilla
     * @param ConfigurarTiposDocumentalesRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la configuración
     *
     * @urlParam id integer required El ID de la ventanilla. Example: 1
     * @bodyParam tipos_documentales array required Array de IDs de tipos documentales. Example: [1, 2, 3]
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Tipos documentales configurados exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Ventanilla no encontrada"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "tipos_documentales": ["Los tipos documentales son obligatorios."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al configurar los tipos documentales",
     *   "error": "Error message"
     * }
     */
    public function configurarTiposDocumentales($id, ConfigurarTiposDocumentalesRequest $request)
    {
        try {
            DB::beginTransaction();

            $ventanilla = VentanillaUnica::findOrFail($id);
            $ventanilla->tiposDocumentales()->sync($request->tipos_documentales);

            DB::commit();

            return $this->successResponse(null, 'Tipos documentales configurados exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al configurar los tipos documentales', $e->getMessage(), 500);
        }
    }

    /**
     * Lista los tipos documentales permitidos en una ventanilla.
     *
     * Este método permite obtener todos los tipos documentales que están
     * permitidos en una ventanilla específica.
     *
     * @param int $id ID de la ventanilla
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los tipos documentales
     *
     * @urlParam id integer required El ID de la ventanilla. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Tipos documentales obtenidos exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "codigo": "01",
     *       "nombre": "Correspondencia",
     *       "descripcion": "Correspondencia general"
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Ventanilla no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener los tipos documentales",
     *   "error": "Error message"
     * }
     */
    public function listarTiposDocumentales($id)
    {
        try {
            $ventanilla = VentanillaUnica::with('tiposDocumentales')->findOrFail($id);

            return $this->successResponse(
                $ventanilla->tiposDocumentales,
                'Tipos documentales obtenidos exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los tipos documentales', $e->getMessage(), 500);
        }
    }
}
