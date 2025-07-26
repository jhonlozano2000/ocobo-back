<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\StoreConfigSedeRequest;
use App\Http\Requests\Configuracion\UpdateConfigSedeRequest;
use App\Models\Configuracion\ConfigSede;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigSedeController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todas las sedes del sistema.
     *
     * Este método retorna todas las sedes registradas en el sistema.
     * Es útil para interfaces de administración donde se necesita mostrar
     * la lista completa de sedes disponibles.
     *
     * @param Request $request La solicitud HTTP que puede contener parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de sedes
     *
     * @queryParam search string Buscar por nombre, dirección o código. Example: "Principal"
     * @queryParam estado integer Filtrar por estado (0 inactivo, 1 activo). Example: 1
     * @queryParam divi_poli_id integer Filtrar por división política. Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de sedes obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombre": "Sede Principal",
     *       "direccion": "Calle 123 #45-67",
     *       "telefono": "1234567",
     *       "email": "sede@example.com",
     *       "estado": 1,
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de sedes",
     *   "error": "Error message"
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = ConfigSede::with('divisionPolitica');

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('direccion', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%");
                });
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('divi_poli_id')) {
                $query->where('divi_poli_id', $request->divi_poli_id);
            }

            // Ordenar por nombre
            $query->orderBy('nombre', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $sedes = $query->paginate($perPage);
            } else {
                $sedes = $query->get();
            }

            return $this->successResponse($sedes, 'Listado de sedes obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de sedes', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva sede en el sistema.
     *
     * Este método permite crear una nueva sede con validación de datos
     * y conversión automática del campo estado.
     *
     * @param StoreConfigSedeRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la sede creada
     *
     * @bodyParam nombre string required Nombre de la sede. Example: "Sede Principal"
     * @bodyParam codigo string required Código único de la sede. Example: "SEDE001"
     * @bodyParam direccion string required Dirección de la sede. Example: "Calle 123 #45-67"
     * @bodyParam telefono string Teléfono de la sede. Example: "1234567"
     * @bodyParam email string Email de la sede. Example: "sede@example.com"
     * @bodyParam ubicacion string Ubicación de la sede. Example: "Centro de la ciudad"
     * @bodyParam divi_poli_id integer ID de la división política. Example: 1
     * @bodyParam estado boolean Estado de la sede (activo/inactivo). Example: true
     * @bodyParam numeracion_unificada boolean Numeración unificada de radicados. Example: true
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Sede creada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombre": "Sede Principal",
     *     "direccion": "Calle 123 #45-67",
     *     "telefono": "1234567",
     *     "email": "sede@example.com",
     *     "estado": 1
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "nombre": ["El nombre de la sede es obligatorio."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear la sede",
     *   "error": "Error message"
     * }
     */
    public function store(StoreConfigSedeRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $sede = ConfigSede::create($validatedData);

            DB::commit();

            return $this->successResponse(
                $sede->load('divisionPolitica'),
                'Sede creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear la sede', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una sede específica por su ID.
     *
     * Este método permite obtener los detalles de una sede específica.
     * Es útil para mostrar información detallada o para formularios de edición.
     *
     * @param ConfigSede $sede La sede a obtener (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la sede
     *
     * @urlParam sede integer required El ID de la sede. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Sede encontrada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombre": "Sede Principal",
     *     "direccion": "Calle 123 #45-67",
     *     "telefono": "1234567",
     *     "email": "sede@example.com",
     *     "estado": 1
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Sede no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la sede",
     *   "error": "Error message"
     * }
     */
    public function show(ConfigSede $sede)
    {
        try {
            return $this->successResponse(
                $sede->load('divisionPolitica'),
                'Sede encontrada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la sede', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una sede existente en el sistema.
     *
     * Este método permite modificar los datos de una sede existente,
     * incluyendo conversión automática del campo estado.
     *
     * @param UpdateConfigSedeRequest $request La solicitud HTTP validada
     * @param ConfigSede $sede La sede a actualizar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la sede actualizada
     *
     * @bodyParam nombre string Nombre de la sede. Example: "Sede Principal"
     * @bodyParam codigo string Código único de la sede. Example: "SEDE001"
     * @bodyParam direccion string Dirección de la sede. Example: "Calle 123 #45-67"
     * @bodyParam telefono string Teléfono de la sede. Example: "1234567"
     * @bodyParam email string Email de la sede. Example: "sede@example.com"
     * @bodyParam ubicacion string Ubicación de la sede. Example: "Centro de la ciudad"
     * @bodyParam divi_poli_id integer ID de la división política. Example: 1
     * @bodyParam estado boolean Estado de la sede (activo/inactivo). Example: true
     * @bodyParam numeracion_unificada boolean Numeración unificada de radicados. Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Sede actualizada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombre": "Sede Principal",
     *     "direccion": "Calle 123 #45-67",
     *     "telefono": "1234567",
     *     "email": "sede@example.com",
     *     "estado": 1
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "email": ["El formato del email no es válido."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la sede",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateConfigSedeRequest $request, ConfigSede $sede)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $sede->update($validatedData);

            DB::commit();

            return $this->successResponse(
                $sede->load('divisionPolitica'),
                'Sede actualizada exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la sede', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una sede del sistema.
     *
     * Este método permite eliminar una sede específica del sistema.
     * Se recomienda verificar que no tenga dependencias antes de eliminar.
     *
     * @param ConfigSede $sede La sede a eliminar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam sede integer required El ID de la sede a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Sede eliminada exitosamente"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar la sede",
     *   "error": "Error message"
     * }
     */
    public function destroy(ConfigSede $sede)
    {
        try {
            DB::beginTransaction();

            $sede->delete();

            DB::commit();

            return $this->successResponse(null, 'Sede eliminada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar la sede', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas de sedes del sistema.
     *
     * Este método proporciona estadísticas generales sobre las sedes del sistema,
     * incluyendo el total de sedes y sedes activas/inactivas.
     * Es útil para dashboards de administración.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "total_sedes": 5,
     *     "sedes_activas": 4,
     *     "sedes_inactivas": 1
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las estadísticas",
     *   "error": "Error message"
     * }
     */
    public function estadisticas()
    {
        try {
            $totalSedes = ConfigSede::count();
            $sedesActivas = ConfigSede::where('estado', 1)->count();
            $sedesInactivas = ConfigSede::where('estado', 0)->count();

            $estadisticas = [
                'total_sedes' => $totalSedes,
                'sedes_activas' => $sedesActivas,
                'sedes_inactivas' => $sedesInactivas,
            ];

            return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }
}
