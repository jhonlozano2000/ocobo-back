<?php

namespace App\Http\Controllers\VentanillaUnica\Internos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoDestinatarios;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaInternoDestinatariosController extends Controller
{
    use ApiResponseTrait;
    /**
     * Obtiene un listado de todos los destinatarios.
     *
     * @return JsonResponse Respuesta JSON con el listado de destinatarios
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de destinatarios obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "radica_interno_id": 1,
     *       "users_cargos_id": 1,
     *       "fechor_visto": "2024-01-01T10:00:00.000000Z",
     *       "created_at": "2024-01-01T10:00:00.000000Z",
     *       "updated_at": "2024-01-01T10:00:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "No hay destinatarios asignados"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de destinatarios",
     *   "error": "Error message"
     * }
     */
    public function index()
    {
        try {
            $destinatarios = VentanillaRadicaInternoDestinatarios::all();

            return $this->successResponse($destinatarios, 'Listado de destinatarios obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de destinatarios', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request La solicitud HTTP validada
     * @return JsonResponse Respuesta JSON con el destinatario creado
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Destinatario creado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "users_cargos_id": 1,
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
     *     "users_cargos_id": ["El destinatario es obligatorio."]
     *   }
     * }
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $destinatario = VentanillaRadicaInternoDestinatarios::create($request->validated());

            DB::commit();

            return $this->successResponse($destinatario, 'Destinatario creado exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el destinatario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un destinatario específico por su ID.
     *
     * @param int $id ID del destinatario
     * @return JsonResponse Respuesta JSON con el destinatario
     *
     * @urlParam id integer required El ID del destinatario. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Destinatario encontrado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "users_cargos_id": 1,
     *     "fechor_visto": "2024-01-01T10:00:00.000000Z",
     *     "created_at": "2024-01-01T10:00:00.000000Z",
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Destinatario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el destinatario",
     *   "error": "Error message"
     * }
     */
    public function show($id)
    {
        try {
            $destinatario = VentanillaRadicaInternoDestinatarios::find($id);

            if (!$destinatario) {
                return $this->errorResponse('Destinatario no encontrado', null, 404);
            }

            return $this->successResponse($destinatario, 'Destinatario encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el destinatario', $e->getMessage(), 500);
        }
    }


    /**
     * Obtiene los destinatarios de una radicación interna específica.
     *
     * @param int $radica_interno_id ID de la radicación interna
     * @return JsonResponse Respuesta JSON con los destinatarios
     *
     * @urlParam radica_interno_id integer required El ID de la radicación interna. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de destinatarios obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "radica_interno_id": 1,
     *       "users_cargos_id": 1,
     *       "fechor_visto": "2024-01-01T10:00:00.000000Z",
     *       "created_at": "2024-01-01T10:00:00.000000Z",
     *       "updated_at": "2024-01-01T10:00:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "No hay destinatarios asignados para esta radicación interna"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de destinatarios",
     *   "error": "Error message"
     * }
     */
    public function getByRadicado($radica_interno_id)
    {
        try {
            $destinatarios = VentanillaRadicaInternoDestinatarios::with('userCargo.user')
                ->where('radica_interno_id', $radica_interno_id)
                ->orderBy('created_at', 'desc')
                ->get();

            $data = $destinatarios->map(fn($d) => $d->getInfoDestinatario())->filter()->values();

            return $this->successResponse($data, 'Listado de destinatarios obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de destinatarios', $e->getMessage(), 500);
        }
    }


    /**
     * Actualiza un destinatario específico por su ID con los datos enviados.
     *
     * @param int $id ID del destinatario
     * @param Request $request La solicitud HTTP validada
     * @return JsonResponse Respuesta JSON con el destinatario actualizado
     *
     * @urlParam id integer required El ID del destinatario. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Destinatario actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "users_cargos_id": 1,
     *     "fechor_visto": "2024-01-01T10:00:00.000000Z",
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "radica_interno_id": ["El radicado interno es obligatorio."],
     *     "users_cargos_id": ["El destinatario es obligatorio."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el destinatario",
     *   "error": "Error message"
     * }
     */
    public function update($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $destinatario = VentanillaRadicaInternoDestinatarios::find($id);

            if (!$destinatario) {
                return $this->errorResponse('Destinatario no encontrado', null, 404);
            }

            $destinatario->update($request->validated());

            DB::commit();

            return $this->successResponse($destinatario, 'Destinatario actualizado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el destinatario', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un destinatario específico por su ID.
     *
     * @param int $id ID del destinatario
     * @return JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam id integer required El ID del destinatario. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Destinatario eliminado exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Destinatario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar el destinatario",
     *   "error": "Error message"
     * }
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $destinatario = VentanillaRadicaInternoDestinatarios::find($id);

            if (!$destinatario) {
                return $this->errorResponse('Destinatario no encontrado', null, 404);
            }

            $destinatario->delete();

            DB::commit();

            return $this->successResponse(null, 'Destinatario eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el destinatario', $e->getMessage(), 500);
        }
    }

    public function assignToRadicado($radica_interno_id, Request $request)
    {
        try {
            DB::beginTransaction();

            $radicado = \App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno::find($radica_interno_id);

            if (!$radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $request->validate([
                'destinatarios' => 'required|array|min:1',
                'destinatarios.*.users_cargos_id' => 'required|integer',
            ]);

            $destinatariosCreados = [];

            foreach ($request->destinatarios as $item) {
                $destinatario = VentanillaRadicaInternoDestinatarios::create([
                    'radica_interno_id' => (int) $radica_interno_id,
                    'users_cargos_id' => (int) $item['users_cargos_id'],
                ]);
                $destinatariosCreados[] = $destinatario->load('userCargo.user', 'userCargo.cargo');
            }

            DB::commit();

            return $this->successResponse($destinatariosCreados, 'Destinatarios asignados exitosamente', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al asignar destinatarios', $e->getMessage(), 500);
        }
    }
}
