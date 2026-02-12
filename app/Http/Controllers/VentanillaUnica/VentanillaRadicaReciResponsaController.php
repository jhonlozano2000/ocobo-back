<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\VentanillaRadicaReciResponsaRequest;
use App\Http\Requests\Ventanilla\ListResponsablesRequest;
use App\Models\VentanillaUnica\VentanillaRadicaReciResponsa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentanillaRadicaReciResponsaController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> Cores. Recibida -> ';

    public function __construct()
    {
        $this->middleware('can:' . self::PERM . 'Editar')->only(['index', 'store', 'show', 'update', 'destroy', 'getByRadicado', 'assignToRadicado']);
    }

    /**
     * Obtiene un listado de todos los responsables de radicaciones.
     *
     * Este método retorna todos los responsables registrados en el sistema
     * con información detallada de usuarios y radicaciones.
     *
     * @param ListResponsablesRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de responsables
     *
     * @queryParam radica_reci_id integer Filtrar por ID de radicación. Example: 1
     * @queryParam user_id integer Filtrar por ID de usuario. Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de responsables obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "radica_reci_id": 1,
     *       "user_id": 1,
     *       "custodio": true,
     *       "usuarioCargo": {
     *         "id": 1,
     *         "nombres": "Juan",
     *         "apellidos": "Pérez"
     *       },
     *       "radicado": {
     *         "id": 1,
     *         "num_radicado": "20240101-00001"
     *       }
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de responsables",
     *   "error": "Error message"
     * }
     */
    public function index(ListResponsablesRequest $request)
    {
        try {
            $query = VentanillaRadicaReciResponsa::with(['usuarioCargo', 'radicado']);

            // Aplicar filtros si se proporcionan
            if ($request->filled('radica_reci_id')) {
                $query->where('radica_reci_id', $request->radica_reci_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Ordenar por fecha de creación
            $query->orderBy('created_at', 'desc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $responsables = $query->paginate($perPage);
            } else {
                $responsables = $query->get();
            }

            return $this->successResponse($responsables, 'Listado de responsables obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de responsables', $e->getMessage(), 500);
        }
    }

    /**
     * Crea nuevos responsables para una radicación específica.
     *
     * Este método permite asignar múltiples responsables a una radicación
     * en una sola operación, validando que los datos sean un arreglo.
     *
     * @param VentanillaRadicaReciResponsaRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los responsables creados
     *
     * @bodyParam responsables array required Array de responsables. Example: [{"radica_reci_id": 1, "user_id": 1, "custodio": true}]
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Responsables asignados exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "radica_reci_id": 1,
     *       "user_id": 1,
     *       "custodio": true
     *     }
     *   ]
     * }
     *
     * @response 400 {
     *   "status": false,
     *   "message": "Los datos deben ser un arreglo no vacío"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "responsables": ["Los responsables son obligatorios."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al asignar responsables",
     *   "error": "Error message"
     * }
     */
    public function store(VentanillaRadicaReciResponsaRequest $request)
    {
        try {
            DB::beginTransaction();

            // Validar y procesar los datos
            $validatedData = $request->validated();

            // Verificar que se envíe el array de responsables
            if (!isset($validatedData['responsables']) || !is_array($validatedData['responsables']) || empty($validatedData['responsables'])) {
                return $this->errorResponse('Se debe enviar un array de responsables no vacío', null, 400);
            }

            $responsables = $validatedData['responsables'];
            $responsablesCreados = [];

            // Crear cada responsable individualmente para obtener los IDs
            foreach ($responsables as $responsableData) {
                // Asegurar que los tipos de datos sean correctos
                $data = [
                    'radica_reci_id' => (int) $responsableData['radica_reci_id'],
                    'users_cargos_id' => (int) $responsableData['users_cargos_id'],
                    'custodio' => filter_var($responsableData['custodio'], FILTER_VALIDATE_BOOLEAN),
                ];

                $responsable = VentanillaRadicaReciResponsa::create($data);
                $responsablesCreados[] = $responsable->load(['usuarioCargo', 'radicado']);
            }

            DB::commit();

            return $this->successResponse($responsablesCreados, 'Responsables asignados exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al asignar responsables', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un responsable específico por su ID.
     *
     * Este método permite obtener los detalles de un responsable específico
     * incluyendo información del usuario y la radicación.
     *
     * @param int $id ID del responsable
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el responsable
     *
     * @urlParam id integer required El ID del responsable. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Responsable encontrado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_reci_id": 1,
     *     "user_id": 1,
     *     "custodio": true,
     *     "usuarioCargo": {
     *       "id": 1,
     *       "nombres": "Juan",
     *       "apellidos": "Pérez"
     *     },
     *     "radicado": {
     *       "id": 1,
     *       "num_radicado": "20240101-00001"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Responsable no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el responsable",
     *   "error": "Error message"
     * }
     */
    public function show($id)
    {
        try {
            $responsable = VentanillaRadicaReciResponsa::with(['usuarioCargo', 'radicado'])->find($id);

            if (!$responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            return $this->successResponse($responsable, 'Responsable encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un responsable existente en el sistema.
     *
     * Este método permite modificar los datos de un responsable existente,
     * incluyendo conversión automática del campo custodio.
     *
     * @param int $id ID del responsable
     * @param VentanillaRadicaReciResponsaRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el responsable actualizado
     *
     * @urlParam id integer required El ID del responsable. Example: 1
     * @bodyParam radica_reci_id integer ID de la radicación. Example: 1
     * @bodyParam user_id integer ID del usuario. Example: 1
     * @bodyParam custodio boolean Indica si es custodio. Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Responsable actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_reci_id": 1,
     *     "user_id": 1,
     *     "custodio": true,
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Responsable no encontrado"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "radica_reci_id": ["La radicación es obligatoria."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el responsable",
     *   "error": "Error message"
     * }
     */
    public function update($id, VentanillaRadicaReciResponsaRequest $request)
    {
        try {
            DB::beginTransaction();

            $responsable = VentanillaRadicaReciResponsa::find($id);

            if (!$responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $validatedData = $request->validated();

            // Convertir custodio a booleano si se proporciona
            if (isset($validatedData['custodio'])) {
                $validatedData['custodio'] = filter_var($validatedData['custodio'], FILTER_VALIDATE_BOOLEAN);
            }

            $responsable->update($validatedData);

            DB::commit();

            return $this->successResponse(
                $responsable->load(['usuarioCargo', 'radicado']),
                'Responsable actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un responsable del sistema.
     *
     * Este método permite eliminar un responsable específico del sistema.
     * Se recomienda verificar que no tenga dependencias antes de eliminar.
     *
     * @param int $id ID del responsable
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam id integer required El ID del responsable a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Responsable eliminado exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Responsable no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar el responsable",
     *   "error": "Error message"
     * }
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $responsable = VentanillaRadicaReciResponsa::find($id);

            if (!$responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $responsable->delete();

            DB::commit();

            return $this->successResponse(null, 'Responsable eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene los responsables de una radicación específica.
     *
     * Este método permite obtener todos los responsables asignados
     * a una radicación específica.
     *
     * @param int $radica_reci_id ID de la radicación
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los responsables
     *
     * @urlParam radica_reci_id integer required El ID de la radicación. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Responsables de la radicación obtenidos exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "radica_reci_id": 1,
     *       "user_id": 1,
     *       "custodio": true,
     *       "usuarioCargo": {
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
     *   "message": "No hay responsables asignados para este radicado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener los responsables",
     *   "error": "Error message"
     * }
     */
    public function getByRadicado($radica_reci_id)
    {
        try {
            $responsables = VentanillaRadicaReciResponsa::with('usuarioCargo')
                ->where('radica_reci_id', $radica_reci_id)
                ->get();

            if ($responsables->isEmpty()) {
                return $this->errorResponse('No hay responsables asignados para este radicado', null, 404);
            }

            return $this->successResponse($responsables, 'Responsables de la radicación obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los responsables', $e->getMessage(), 500);
        }
    }

    /**
     * Asigna responsables a una radicación específica.
     *
     * Este método permite asignar múltiples responsables a una radicación específica
     * usando el ID de la radicación en la URL.
     *
     * @param int $radica_reci_id ID de la radicación
     * @param Request $request Array de responsables a asignar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los responsables asignados
     *
     * @urlParam radica_reci_id integer required El ID de la radicación. Example: 1
     * @bodyParam responsables array required Array de responsables. Example: [{"user_id": 1, "custodio": true}]
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Responsables asignados exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "radica_reci_id": 1,
     *       "user_id": 1,
     *       "custodio": true
     *     }
     *   ]
     * }
     *
     * @response 400 {
     *   "status": false,
     *   "message": "Se debe enviar un array de responsables no vacío"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "responsables": ["Los responsables son obligatorios."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al asignar responsables",
     *   "error": "Error message"
     *   }
     */
    public function assignToRadicado($radica_reci_id, VentanillaRadicaReciResponsaRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            $responsables = $validatedData['responsables'];
            $responsablesCreados = [];

            // Crear cada responsable individualmente para obtener los IDs
            foreach ($responsables as $responsableData) {
                $responsable = VentanillaRadicaReciResponsa::create([
                    'radica_reci_id' => $radica_reci_id,
                    'users_cargos_id' => $responsableData['users_cargos_id'],
                    'custodio' => $responsableData['custodio']
                ]);
                $responsablesCreados[] = $responsable->load(['usuarioCargo', 'radicado']);
            }

            DB::commit();

            return $this->successResponse($responsablesCreados, 'Responsables asignados exitosamente', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al asignar responsables', $e->getMessage(), 500);
        }
    }
}
