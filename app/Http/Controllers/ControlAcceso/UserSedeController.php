<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\ControlAcceso\StoreUserSedeRequest;
use App\Http\Requests\ControlAcceso\UpdateUserSedeRequest;
use App\Http\Requests\ControlAcceso\ListUserSedeRequest;
use App\Models\User;
use App\Models\Configuracion\ConfigSede;
use App\Models\UsersSede;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserSedeController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todas las relaciones usuario-sede del sistema.
     *
     * Este método retorna todas las relaciones usuario-sede registradas en el sistema.
     * Es útil para interfaces de administración donde se necesita mostrar
     * las asignaciones de usuarios a sedes.
     *
     * @param ListUserSedeRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de relaciones
     *
     * @queryParam user_id integer Filtrar por ID de usuario. Example: 1
     * @queryParam sede_id integer Filtrar por ID de sede. Example: 1
     * @queryParam estado integer Filtrar por estado (0 inactivo, 1 activo). Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de relaciones usuario-sede obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 1,
     *       "sede_id": 1,
     *       "estado": true,
     *       "observaciones": "Asignación principal",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z",
     *       "user": {
     *         "id": 1,
     *         "nombres": "Juan",
     *         "apellidos": "Pérez"
     *       },
     *       "sede": {
     *         "id": 1,
     *         "nombre": "Sede Principal",
     *         "codigo": "SEDE001"
     *       }
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de relaciones usuario-sede",
     *   "error": "Error message"
     * }
     */
    public function index(ListUserSedeRequest $request)
    {
        try {
            $query = UsersSede::with(['user', 'sede']);

            // Aplicar filtros si se proporcionan
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            // Ordenar por fecha de creación
            $query->orderBy('created_at', 'desc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $relaciones = $query->paginate($perPage);
            } else {
                $relaciones = $query->get();
            }

            return $this->successResponse($relaciones, 'Listado de relaciones usuario-sede obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de relaciones usuario-sede', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva relación usuario-sede en el sistema.
     *
     * Este método permite asignar un usuario a una sede específica.
     * La relación incluye un estado (activo/inactivo) y observaciones opcionales.
     *
     * @param StoreUserSedeRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la relación creada
     *
     * @bodyParam user_id integer required ID del usuario a asignar. Example: 1
     * @bodyParam sede_id integer required ID de la sede a asignar. Example: 1
     * @bodyParam estado boolean Estado de la relación (activo/inactivo). Example: true
     * @bodyParam observaciones string Observaciones sobre la asignación. Example: "Asignación principal"
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Relación usuario-sede creada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "sede_id": 1,
     *     "estado": true,
     *     "observaciones": "Asignación principal",
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "user_id": ["El usuario es obligatorio."],
     *     "sede_id": ["La sede es obligatoria."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear la relación usuario-sede",
     *   "error": "Error message"
     * }
     */
    public function store(StoreUserSedeRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            // Verificar que no exista ya la relación
            $existingRelation = UsersSede::where('user_id', $validatedData['user_id'])
                ->where('sede_id', $validatedData['sede_id'])
                ->first();

            if ($existingRelation) {
                return $this->errorResponse('La relación usuario-sede ya existe', null, 422);
            }

            $relacion = UsersSede::create($validatedData);

            DB::commit();

            return $this->successResponse(
                $relacion->load(['user', 'sede']),
                'Relación usuario-sede creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear la relación usuario-sede', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una relación usuario-sede específica por su ID.
     *
     * Este método permite obtener los detalles de una relación usuario-sede específica.
     * Es útil para mostrar información detallada o para formularios de edición.
     *
     * @param UsersSede $userSede La relación a obtener (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la relación
     *
     * @urlParam userSede integer required El ID de la relación. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Relación usuario-sede encontrada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "sede_id": 1,
     *     "estado": true,
     *     "observaciones": "Asignación principal",
     *     "user": {
     *       "id": 1,
     *       "nombres": "Juan",
     *       "apellidos": "Pérez"
     *     },
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
     *   "message": "Relación usuario-sede no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la relación usuario-sede",
     *   "error": "Error message"
     * }
     */
    public function show(UsersSede $userSede)
    {
        try {
            return $this->successResponse(
                $userSede->load(['user', 'sede']),
                'Relación usuario-sede encontrada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la relación usuario-sede', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una relación usuario-sede existente en el sistema.
     *
     * Este método permite modificar los datos de una relación usuario-sede existente,
     * incluyendo conversión automática del campo estado.
     *
     * @param UpdateUserSedeRequest $request La solicitud HTTP validada
     * @param UsersSede $userSede La relación a actualizar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la relación actualizada
     *
     * @bodyParam estado boolean Estado de la relación (activo/inactivo). Example: true
     * @bodyParam observaciones string Observaciones sobre la asignación. Example: "Asignación actualizada"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Relación usuario-sede actualizada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "sede_id": 1,
     *     "estado": true,
     *     "observaciones": "Asignación actualizada",
     *     "updated_at": "2024-01-01T00:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Relación usuario-sede no encontrada"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "estado": ["El estado debe ser 0, 1, true o false."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la relación usuario-sede",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateUserSedeRequest $request, UsersSede $userSede)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $userSede->update($validatedData);

            DB::commit();

            return $this->successResponse(
                $userSede->load(['user', 'sede']),
                'Relación usuario-sede actualizada exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la relación usuario-sede', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una relación usuario-sede del sistema.
     *
     * Este método permite eliminar una relación usuario-sede específica del sistema.
     * Se recomienda verificar que no tenga dependencias antes de eliminar.
     *
     * @param UsersSede $userSede La relación a eliminar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam userSede integer required El ID de la relación a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Relación usuario-sede eliminada exitosamente"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar la relación usuario-sede",
     *   "error": "Error message"
     * }
     */
    public function destroy(UsersSede $userSede)
    {
        try {
            DB::beginTransaction();

            $userSede->delete();

            DB::commit();

            return $this->successResponse(null, 'Relación usuario-sede eliminada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar la relación usuario-sede', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene las sedes asignadas a un usuario específico.
     *
     * Este método permite obtener todas las sedes que están asignadas a un usuario,
     * incluyendo información detallada de cada sede.
     *
     * @param int $userId ID del usuario
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las sedes del usuario
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     * @queryParam activas_only boolean Filtrar solo sedes activas. Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Sedes del usuario obtenidas exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombre": "Sede Principal",
     *       "codigo": "SEDE001",
     *       "pivot": {
     *         "estado": true,
     *         "observaciones": "Asignación principal"
     *       }
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     */
    public function getUserSedes($userId, Request $request)
    {
        try {
            $user = User::findOrFail($userId);

            $query = $user->sedes();

            if ($request->boolean('activas_only', true)) {
                $query = $user->sedesActivas();
            }

            $sedes = $query->get();

            return $this->successResponse($sedes, 'Sedes del usuario obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las sedes del usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Sincroniza las sedes asignadas a un usuario específico.
     *
     * Este método permite actualizar las sedes asignadas a un usuario, eliminando
     * las asignaciones que no están en el array proporcionado y agregando las nuevas.
     * Cada sede puede incluir información adicional como estado y observaciones.
     *
     * @param int $userId ID del usuario
     * @param Request $request La solicitud HTTP con el array de sedes
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las sedes actualizadas
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     * @bodyParam sedes array required Array de sedes a asignar. Example: [1, 2, 3]
     * @bodyParam sedes.*.sede_id integer ID de la sede. Example: 1
     * @bodyParam sedes.*.estado boolean Estado de la relación (opcional, por defecto true). Example: true
     * @bodyParam sedes.*.observaciones string Observaciones sobre la asignación (opcional). Example: "Asignación principal"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Sedes del usuario sincronizadas exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombre": "Sede Principal",
     *       "codigo": "SEDE001",
     *       "pivot": {
     *         "estado": true,
     *         "observaciones": "Asignación principal"
     *       }
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "sedes": ["El campo sedes es obligatorio."]
     *   }
     * }
     */
    public function updateUserSedes($userId, Request $request)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);

            // Validar que se envíe el array de sedes
            $request->validate([
                'sedes' => 'required|array',
                'sedes.*' => 'nullable|integer|exists:config_sedes,id'
            ], [
                'sedes.required' => 'El campo sedes es obligatorio.',
                'sedes.array' => 'El campo sedes debe ser un array.',
                'sedes.*.integer' => 'Cada sede debe ser un ID válido.',
                'sedes.*.exists' => 'Una o más sedes no existen.'
            ]);

            $sedesIds = $request->input('sedes', []);

            // Si se envía un array de objetos con sede_id, extraer solo los IDs
            if (!empty($sedesIds) && is_array($sedesIds[0] ?? null) && isset($sedesIds[0]['sede_id'])) {
                $sedesIds = array_column($sedesIds, 'sede_id');
            }

            // Filtrar valores nulos y vacíos
            $sedesIds = array_filter($sedesIds, function ($id) {
                return !is_null($id) && $id !== '';
            });

            // Preparar datos para sincronización con información adicional
            $syncData = [];
            foreach ($sedesIds as $sedeId) {
                $syncData[$sedeId] = [
                    'estado' => true,
                    'observaciones' => null
                ];
            }

            // Si se envía información adicional por sede, procesarla
            if ($request->has('sedes') && is_array($request->sedes)) {
                foreach ($request->sedes as $sedeData) {
                    if (isset($sedeData['sede_id']) && isset($syncData[$sedeData['sede_id']])) {
                        if (isset($sedeData['estado'])) {
                            $syncData[$sedeData['sede_id']]['estado'] = filter_var($sedeData['estado'], FILTER_VALIDATE_BOOLEAN);
                        }
                        if (isset($sedeData['observaciones'])) {
                            $syncData[$sedeData['sede_id']]['observaciones'] = $sedeData['observaciones'];
                        }
                    }
                }
            }

            // Sincronizar las sedes del usuario
            $user->sedes()->sync($syncData);

            DB::commit();

            // Recargar las sedes del usuario
            $sedes = $user->sedes()->get();

            return $this->successResponse($sedes, 'Sedes del usuario sincronizadas exitosamente');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al sincronizar las sedes del usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene los usuarios asignados a una sede específica.
     *
     * Este método permite obtener todos los usuarios que están asignados a una sede,
     * incluyendo información detallada de cada usuario.
     *
     * @param int $sedeId ID de la sede
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los usuarios de la sede
     *
     * @urlParam sedeId integer required El ID de la sede. Example: 1
     * @queryParam activos_only boolean Filtrar solo usuarios activos. Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Usuarios de la sede obtenidos exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombres": "Juan",
     *       "apellidos": "Pérez",
     *       "pivot": {
     *         "estado": true,
     *         "observaciones": "Asignación principal"
     *       }
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Sede no encontrada"
     * }
     */
    public function getSedeUsers($sedeId, Request $request)
    {
        try {
            $sede = ConfigSede::findOrFail($sedeId);

            $query = $sede->usuarios();

            if ($request->boolean('activos_only', true)) {
                $query = $sede->usuariosActivos();
            }

            $usuarios = $query->get();

            return $this->successResponse($usuarios, 'Usuarios de la sede obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los usuarios de la sede', $e->getMessage(), 500);
        }
    }
}
