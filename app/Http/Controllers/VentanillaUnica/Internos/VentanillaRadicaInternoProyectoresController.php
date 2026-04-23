<?php

namespace App\Http\Controllers\VentanillaUnica\Internos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoProyectores;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaInternoProyectoresController extends Controller
{
    use ApiResponseTrait;
    /**
     * Obtiene un listado de todos los proyectores.
     *
     * @return JsonResponse Respuesta JSON con el listado de proyectores
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de proyectores obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "radica_interno_id": 1,
     *       "users_cargos_id": 1,
     *       "created_at": "2024-01-01T10:00:00.000000Z",
     *       "updated_at": "2024-01-01T10:00:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "No hay proyectores asignados"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de proyectores",
     *   "error": "Error message"
     * }
     */
    public function index()
    {
        try {
            $proyectores = VentanillaRadicaInternoProyectores::all();

            return $this->successResponse($proyectores, 'Listado de proyectores obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de proyectores', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo proyector.
     *
     * @param Request $request La solicitud HTTP validada
     * @return JsonResponse Respuesta JSON con el proyector creado
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Proyector creado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "users_cargos_id": 1,
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
     *     "users_cargos_id": ["El proyector es obligatorio."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear el proyector",
     *   "error": "Error message"
     * }
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $proyector = VentanillaRadicaInternoProyectores::create($request->validated());

            DB::commit();

            return $this->successResponse($proyector, 'Proyector creado exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el proyector', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un proyector específico por su ID.
     *
     * @param int $id ID del proyector
     * @return JsonResponse Respuesta JSON con el proyector
     *
     * @urlParam id integer required El ID del proyector. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Proyector encontrado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "users_cargos_id": 1,
     *     "created_at": "2024-01-01T10:00:00.000000Z",
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Proyector no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el proyector",
     *   "error": "Error message"
     * }
     */
    public function show($id)
    {
        try {
            $proyector = VentanillaRadicaInternoProyectores::find($id);

            if (!$proyector) {
                return $this->errorResponse('Proyector no encontrado', null, 404);
            }

            return $this->successResponse($proyector, 'Proyector encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el proyector', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un proyector específico por su ID con los datos enviados.
     *
     * @param int $id ID del proyector
     * @param Request $request La solicitud HTTP validada
     * @return JsonResponse Respuesta JSON con el proyector actualizado
     *
     * @urlParam id integer required El ID del proyector. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Proyector actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "radica_interno_id": 1,
     *     "users_cargos_id": 1,
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
     *     "users_cargos_id": ["El proyector es obligatorio."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el proyector",
     *   "error": "Error message"
     * }
     */
    public function update($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $proyector = VentanillaRadicaInternoProyectores::find($id);

            if (!$proyector) {
                return $this->errorResponse('Proyector no encontrado', null, 404);
            }

            $proyector->update($request->validated());

            DB::commit();

            return $this->successResponse($proyector, 'Proyector actualizado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el proyector', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un proyector específico por su ID.
     *
     * @param int $id ID del proyector
     * @return JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam id integer required El ID del proyector. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Proyector eliminado exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Proyector no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar el proyector",
     *   "error": "Error message"
     * }
     */
    public function destroy($id)
    {
        try {
            $proyector = VentanillaRadicaInternoProyectores::find($id);

            if (!$proyector) {
                return $this->errorResponse('Proyector no encontrado', null, 404);
            }

            $proyector->delete();

            DB::commit();

            return $this->successResponse(null, 'Proyector eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el proyector', $e->getMessage(), 500);
        }
    }

    public function getByRadicado($radica_interno_id)
    {
        try {
            $proyectores = VentanillaRadicaInternoProyectores::with(['userCargo.user', 'userCargo.cargo'])
                ->where('radica_interno_id', $radica_interno_id)
                ->orderBy('created_at', 'desc')
                ->get();

$data = $proyectores->map(function ($p) {
                $user = $p->userCargo?->user;
                $cargo = $p->userCargo?->cargo;
                return [
                    'id' => $p->id,
                    'radica_interno_id' => $p->radica_interno_id,
                    'users_cargos_id' => $p->users_cargos_id,
                    'usuario' => $user ? [
                        'nombres' => $user->nombres,
                        'apellidos' => $user->apellidos,
                        'nombre_completo' => trim($user->nombres . ' ' . $user->apellidos)
                    ] : null,
                    'cargo' => $cargo ? [
                        'id' => $cargo->id,
                        'nombre' => $cargo->nom_organico,
                        'nom_organico' => $cargo->nom_organico,
                        'codigo' => $cargo->cod_organico
                    ] : null,
                    'created_at' => $p->created_at,
                ];
            })->values();

            return $this->successResponse($data, 'Proyectores del radicado obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los proyectores', $e->getMessage(), 500);
        }
    }

    public function assignToRadicado($radica_interno_id, Request $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaInterno::find($radica_interno_id);

            if (!$radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $request->validate([
                'proyectores' => 'required|array|min:1',
                'proyectores.*.users_cargos_id' => 'required|integer',
            ]);

            $proyectoresCreados = [];

            foreach ($request->proyectores as $item) {
                $proyector = VentanillaRadicaInternoProyectores::create([
                    'radica_interno_id' => (int) $radica_interno_id,
                    'users_cargos_id' => (int) $item['users_cargos_id'],
                ]);
                $proyectoresCreados[] = $proyector->load(['userCargo.user', 'userCargo.cargo']);
            }

            DB::commit();

            return $this->successResponse($proyectoresCreados, 'Proyectores asignados exitosamente', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al asignar proyectores', $e->getMessage(), 500);
        }
    }
}
