<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\AsignarPermisosVentanillaRequest;
use App\Http\Requests\Ventanilla\ListVentanillasPermitidasRequest;
use App\Models\User;
use App\Models\VentanillaUnica\VentanillaUnica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermisosVentanillaUnicaController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('can:Config - Ventanillas -> Listar')->only(['listarUsuariosPermitidos', 'listarVentanillasPermitidas']);
        $this->middleware('can:Config - Ventanillas -> Editar')->only(['asignarPermisos', 'revocarPermisos']);
    }

    /**
     * Asigna permisos de radicación a usuarios en una ventanilla específica.
     *
     * Este método permite asignar o actualizar los permisos de radicación
     * para usuarios específicos en una ventanilla. Los usuarios asignados
     * podrán realizar radicaciones en esa ventanilla.
     *
     * @param int $ventanillaId ID de la ventanilla
     * @param AsignarPermisosVentanillaRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la asignación
     *
     * @urlParam ventanillaId integer required El ID de la ventanilla. Example: 1
     * @bodyParam usuarios array required Array de IDs de usuarios a asignar. Example: [1, 2, 3]
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Permisos asignados exitosamente"
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
     *     "usuarios": ["Los usuarios son obligatorios."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al asignar permisos",
     *   "error": "Error message"
     * }
     */
    public function asignarPermisos($ventanillaId, AsignarPermisosVentanillaRequest $request)
    {
        try {
            DB::beginTransaction();

            $ventanilla = VentanillaUnica::findOrFail($ventanillaId);
            $ventanilla->usuariosPermitidos()->sync($request->usuarios);

            DB::commit();

            return $this->successResponse(null, 'Permisos asignados exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al asignar permisos', $e->getMessage(), 500);
        }
    }

    /**
     * Lista las ventanillas en las que un usuario puede radicar.
     *
     * Este método permite obtener todas las ventanillas donde un usuario
     * específico tiene permisos para realizar radicaciones.
     *
     * @param int $usuarioId ID del usuario
     * @param ListVentanillasPermitidasRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las ventanillas permitidas
     *
     * @urlParam usuarioId integer required El ID del usuario. Example: 1
     * @queryParam sede_id integer Filtrar por sede específica. Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Ventanillas permitidas obtenidas exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombre": "Ventanilla Principal",
     *       "descripcion": "Ventanilla principal de la sede",
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
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las ventanillas permitidas",
     *   "error": "Error message"
     * }
     */
    public function listarVentanillasPermitidas($usuarioId, ListVentanillasPermitidasRequest $request)
    {
        try {
            $usuario = User::findOrFail($usuarioId);

            $query = $usuario->ventanillasPermitidas()->with('sede');

            // Filtrar por sede si se proporciona
            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
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

            return $this->successResponse($ventanillas, 'Ventanillas permitidas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las ventanillas permitidas', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene los usuarios que tienen permisos en una ventanilla específica.
     *
     * Este método permite obtener todos los usuarios que tienen permisos
     * para radicar en una ventanilla específica.
     *
     * @param int $ventanillaId ID de la ventanilla
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los usuarios permitidos
     *
     * @urlParam ventanillaId integer required El ID de la ventanilla. Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Usuarios permitidos obtenidos exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombres": "Juan",
     *       "apellidos": "Pérez",
     *       "email": "juan.perez@example.com"
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
     *   "message": "Error al obtener los usuarios permitidos",
     *   "error": "Error message"
     * }
     */
    public function listarUsuariosPermitidos($ventanillaId, Request $request)
    {
        try {
            $ventanilla = VentanillaUnica::findOrFail($ventanillaId);

            $query = $ventanilla->usuariosPermitidos();

            // Ordenar por nombres
            $query->orderBy('nombres', 'asc')->orderBy('apellidos', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $usuarios = $query->paginate($perPage);
            } else {
                $usuarios = $query->get();
            }

            return $this->successResponse($usuarios, 'Usuarios permitidos obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los usuarios permitidos', $e->getMessage(), 500);
        }
    }

    /**
     * Revoca los permisos de un usuario en una ventanilla específica.
     *
     * Este método permite revocar los permisos de radicación de un usuario
     * específico en una ventanilla.
     *
     * @param int $ventanillaId ID de la ventanilla
     * @param int $usuarioId ID del usuario
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la revocación
     *
     * @urlParam ventanillaId integer required El ID de la ventanilla. Example: 1
     * @urlParam usuarioId integer required El ID del usuario. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Permisos revocados exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Ventanilla o usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al revocar permisos",
     *   "error": "Error message"
     * }
     */
    public function revocarPermisos($ventanillaId, $usuarioId)
    {
        try {
            DB::beginTransaction();

            $ventanilla = VentanillaUnica::findOrFail($ventanillaId);
            $usuario = User::findOrFail($usuarioId);

            // Verificar que el usuario tenga permisos en la ventanilla
            if (!$ventanilla->usuariosPermitidos()->where('user_id', $usuarioId)->exists()) {
                return $this->errorResponse('El usuario no tiene permisos en esta ventanilla', null, 422);
            }

            $ventanilla->usuariosPermitidos()->detach($usuarioId);

            DB::commit();

            return $this->successResponse(null, 'Permisos revocados exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al revocar permisos', $e->getMessage(), 500);
        }
    }
}
