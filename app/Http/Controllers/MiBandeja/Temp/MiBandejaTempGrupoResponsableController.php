<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\StoreGrupoResponsableRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoResponsable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador para gestionar responsables de grupos colaborativos temporales.
 * Permite agregar, actualizar, eliminar y marcar como terminado a los responsables.
 */
class MiBandejaTempGrupoResponsableController extends Controller
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
     * Lista los responsables de un grupo colaborativo temporal.
     *
     * @param int $grupoId Identificador del grupo
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los responsables del grupo
     */
    public function index($grupoId)
    {
        try {
            $grupo = MiBandejaTemp::find($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $responsables = $grupo->responsables()->with(['user.cargo', 'cargo'])->get();

            return $this->successResponse($responsables, 'Responsables del grupo');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener responsables', $e->getMessage(), 500);
        }
    }

    /**
     * Agrega un nuevo responsable a un grupo colaborativo temporal.
     *
     * @param StoreGrupoResponsableRequest $request Solicitud HTTP con datos del responsable
     * @param int $grupoId Identificador del grupo
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el responsable creado
     */
    public function store(StoreGrupoResponsableRequest $request, $grupoId)
    {
        try {
            $grupo = MiBandejaTemp::find($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            if ($grupo->responsables()->where('user_id', $request->user_id)->exists()) {
                return $this->errorResponse('El usuario ya es responsable en este grupo', null, 422);
            }

            $responsable = MiBandejaTempGrupoResponsable::create([
                'grupo_id' => $grupoId,
                'user_id' => $request->user_id,
                'cargo_id' => $request->cargo_id,
                'es_custodio' => $request->boolean('es_custodio'),
            ]);

            $responsable->load(['user.cargo', 'cargo']);

            return $this->successResponse($responsable, 'Responsable agregado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al agregar responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un responsable existente en un grupo colaborativo temporal.
     *
     * @param Request $request Solicitud HTTP con datos a actualizar
     * @param int $grupoId Identificador del grupo
     * @param int $id Identificador del responsable
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el responsable actualizado
     */
    public function update(Request $request, $grupoId, $id)
    {
        try {
            $responsable = MiBandejaTempGrupoResponsable::where('grupo_id', $grupoId)->find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $responsable->update($request->only(['cargo_id', 'es_custodio', 'subio_plantilla', 'descargo_plantilla', 'fechor_terminado']));

            $responsable->load(['user.cargo', 'cargo']);

            return $this->successResponse($responsable, 'Responsable actualizado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un responsable de un grupo colaborativo temporal.
     *
     * @param int $grupoId Identificador del grupo
     * @param int $id Identificador del responsable a eliminar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con resultado de la operación
     */
    public function destroy($grupoId, $id)
    {
        try {
            $responsable = MiBandejaTempGrupoResponsable::where('grupo_id', $grupoId)->find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $responsable->delete();

            return $this->successResponse(null, 'Responsable eliminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Marca un responsable como terminado en un grupo colaborativo temporal.
     *
     * @param int $grupoId Identificador del grupo
     * @param int $id Identificador del responsable a marcar como terminado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el responsable actualizado
     */
    public function marcarTerminado($grupoId, $id)
    {
        try {
            $responsable = MiBandejaTempGrupoResponsable::where('grupo_id', $grupoId)->find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $responsable->update([
                'estado_tarea' => 'cumplido',
                'fechor_terminado' => now(),
                'descargo_plantilla' => true,
            ]);

            return $this->successResponse($responsable, 'Responsable marcado como terminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al marcar como terminado', $e->getMessage(), 500);
        }
    }
}