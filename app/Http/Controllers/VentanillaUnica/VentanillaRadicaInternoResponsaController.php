<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Models\VentanillaUnica\VentanillaRadicaInternoResponsa;
use App\Models\VentanillaUnica\VentanillRadicaInternoResponsa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaInternoResponsaController extends Controller
{
    /**
     * Obtiene un listado de todos los responsables.
     *
     * @return JsonResponse Respuesta JSON con el listado de responsables
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de responsables obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "radica_interno_id": 1,
     *       "users_cargos_id": 1,
     *       "custodio": true,
     *       "fechor_visto": "2024-01-01T10:00:00.000000Z",
     *       "created_at": "2024-01-01T10:00:00.000000Z",
     *       "updated_at": "2024-01-01T10:00:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "No hay responsables asignados"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de responsables",
     *   "error": "Error message"
     * }
     */
    public function index()
    {
        try {
            $responsables = VentanillaRadicaInternoResponsa::all();

            return $this->successResponse($responsables, 'Listado de responsables obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de responsables', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request La solicitud HTTP validada
     * @return JsonResponse Respuesta JSON con el responsable creado
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Responsable creado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "users_cargos_id": 1,
     *     "custodio": true,
     *     "fechor_visto": "2024-01-01T10:00:00.000000Z",
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
     *     "users_cargos_id": ["El responsable es obligatorio."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear el responsable",
     *   "error": "Error message"
     * }
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $responsable = VentanillaRadicaInternoResponsa::create($request->validated());

            DB::commit();

            return $this->successResponse($responsable, 'Responsable creado exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un responsable específico por su ID.
     *
     * @param int $id ID del responsable
     * @return JsonResponse Respuesta JSON con el responsable
     *
     * @urlParam id integer required El ID del responsable. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Responsable encontrado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "users_cargos_id": 1,
     *     "custodio": true,
     *     "fechor_visto": "2024-01-01T10:00:00.000000Z",
     *     "created_at": "2024-01-01T10:00:00.000000Z",
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
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
            $responsable = VentanillaRadicaInternoResponsa::find($id);

            if (!$responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            return $this->successResponse($responsable, 'Responsable encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un responsable específico por su ID con los datos enviados.
     *
     * @param int $id ID del responsable
     * @param Request $request La solicitud HTTP validada
     * @return JsonResponse Respuesta JSON con el responsable actualizado
     *
     * @urlParam id integer required El ID del responsable. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Responsable actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "users_cargos_id": 1,
     *     "custodio": true,
     *     "fechor_visto": "2024-01-01T10:00:00.000000Z",
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
     *     "users_cargos_id": ["El responsable es obligatorio."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el responsable",
     *   "error": "Error message"
     * }
     */
    public function update($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $responsable = VentanillaRadicaInternoResponsa::find($id);

            if (!$responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $responsable->update($request->validated());

            DB::commit();

            return $this->successResponse($responsable, 'Responsable actualizado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene los responsables de una radicación interna específica.
     *
     * @param int $radica_interno_id ID de la radicación interna
     * @return JsonResponse Respuesta JSON con los responsables
     *
     * @urlParam radica_interno_id integer required El ID de la radicación interna. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de responsables obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "radica_interno_id": 1,
     *       "users_cargos_id": 1,
     *       "custodio": true,
     *       "fechor_visto": "2024-01-01T10:00:00.000000Z",
     *       "created_at": "2024-01-01T10:00:00.000000Z",
     *       "updated_at": "2024-01-01T10:00:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "No hay responsables asignados para esta radicación interna"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de responsables",
     *   "error": "Error message"
     * }
     */
    public function getByRadicado($radica_interno_id)
    {
        try {
            $responsables = VentanillaRadicaInternoResponsa::where('radica_interno_id', $radica_interno_id)->get();

            return $this->successResponse($responsables, 'Listado de responsables obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de responsables', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un responsable específico por su ID.
     *
     * @param int $id ID del responsable
     * @return JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam id integer required El ID del responsable. Example: 1
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
            $responsable = VentanillaRadicaInternoResponsa::find($id);

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
}
