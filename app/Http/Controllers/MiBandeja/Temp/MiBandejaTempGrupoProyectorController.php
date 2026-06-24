<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\StoreGrupoProyectorRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoProyector;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar proyectores de grupos colaborativos temporales.
 * Permite agregar, actualizar, eliminar y marcar como terminado a los proyectores.
 */
class MiBandejaTempGrupoProyectorController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Mi Bandeja - Grupos Colaborativos -> ';

    /**
     * Constructor del controlador.
     * Aplica middleware de permisos para gestión de miembros.
     */
    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Gestionar Miembros')->only(['store', 'destroy', 'update']);
    }

    /**
     * Lista los proyectores de un grupo colaborativo temporal.
     *
     * @param int $grupoId Identificador del grupo
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los proyectores del grupo
     */
    public function index($grupoId)
    {
        try {
            $grupo = \App\Models\MiBandeja\MiBandejaTemp::find($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $proyectores = $grupo->proyectores()->with(['user.cargo', 'cargo'])->get();

            return $this->successResponse($proyectores, 'Proyectores del grupo');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener proyectores', $e->getMessage(), 500);
        }
    }

    /**
     * Agrega un nuevo proyector a un grupo colaborativo temporal.
     *
     * @param \App\Http\Requests\MiBandeja\StoreGrupoProyectorRequest $request Solicitud HTTP con datos del proyector
     * @param int $grupoId Identificador del grupo
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el proyector creado
     */
    public function store(\App\Http\Requests\MiBandeja\StoreGrupoProyectorRequest $request, $grupoId)
    {
        try {
            $grupo = \App\Models\MiBandeja\MiBandejaTemp::find($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            if ($grupo->proyectores()->where('user_id', $request->user_id)->exists()) {
                return $this->errorResponse('El usuario ya es proyector en este grupo', null, 422);
            }

            $proyector = \App\Models\MiBandeja\MiBandejaTempGrupoProyector::create([
                'grupo_id' => $grupoId,
                'user_id' => $request->user_id,
                'cargo_id' => $request->cargo_id,
            ]);

            $proyector->load(['user.cargo', 'cargo']);

            return $this->successResponse($proyector, 'Proyector agregado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al agregar proyector', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un proyector existente en un grupo colaborativo temporal.
     *
     * @param Request $request Solicitud HTTP con datos a actualizar
     * @param int $grupoId Identificador del grupo
     * @param int $id Identificador del proyector
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el proyector actualizado
     */
    public function update(Request $request, $grupoId, $id)
    {
        try {
            $proyector = \App\Models\MiBandeja\MiBandejaTempGrupoProyector::where('grupo_id', $grupoId)->find($id);

            if (! $proyector) {
                return $this->errorResponse('Proyector no encontrado', null, 404);
            }

            $proyector->update($request->only(['cargo_id', 'subio_plantilla', 'descargo_plantilla', 'fechor_terminado']));

            $proyector->load(['user.cargo', 'cargo']);

            return $this->successResponse($proyector, 'Proyector actualizado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar proyector', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un proyector de un grupo colaborativo temporal.
     *
     * @param int $grupoId Identificador del grupo
     * @param int $id Identificador del proyector a eliminar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con resultado de la operación
     */
    public function destroy($grupoId, $id)
    {
        try {
            $proyector = \App\Models\MiBandeja\MiBandejaTempGrupoProyector::where('grupo_id', $grupoId)->find($id);

            if (! $proyector) {
                return $this->errorResponse('Proyector no encontrado', null, 404);
            }

            $proyector->delete();

            return $this->successResponse(null, 'Proyector eliminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar proyector', $e->getMessage(), 500);
        }
    }

    /**
     * Marca un proyector como terminado en un grupo colaborativo temporal.
     *
     * @param int $grupoId Identificador del grupo
     * @param int $id Identificador del proyector a marcar como terminado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el proyector actualizado
     */
    public function marcarTerminado($grupoId, $id)
    {
        try {
            $proyector = \App\Models\MiBandeja\MiBandejaTempGrupoProyector::where('grupo_id', $grupoId)->find($id);

            if (! $proyector) {
                return $this->errorResponse('Proyector no encontrado', null, 404);
            }

            $proyector->update([
                'estado_tarea' => 'cumplido',
                'fechor_terminado' => now(),
                'descargo_plantilla' => true,
            ]);

            return $this->successResponse($proyector, 'Proyector marcado como terminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al marcar como terminado', $e->getMessage(), 500);
        }
    }
}