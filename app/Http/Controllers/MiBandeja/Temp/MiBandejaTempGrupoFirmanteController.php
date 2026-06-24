<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\StoreGrupoFirmanteRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar firmantes de grupos colaborativos temporales.
 * Permite agregar, actualizar, eliminar, marcar como terminado y firmar documentos.
 */
class MiBandejaTempGrupoFirmanteController extends Controller
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
     * Lista los firmantes de un grupo colaborativo temporal.
     *
     * @param int $grupoId Identificador del grupo
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los firmantes del grupo
     */
    public function index($grupoId)
    {
        try {
            $grupo = \App\Models\MiBandeja\MiBandejaTemp::find($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $firmantes = $grupo->firmantes()->with(['user.cargo', 'cargo'])->get();

            return $this->successResponse($firmantes, 'Firmantes del grupo');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener firmantes', $e->getMessage(), 500);
        }
    }

    /**
     * Agrega un nuevo firmante a un grupo colaborativo temporal.
     *
     * @param \App\Http\Requests\MiBandeja\StoreGrupoFirmanteRequest $request Solicitud HTTP con datos del firmante
     * @param int $grupoId Identificador del grupo
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el firmante creado
     */
    public function store(\App\Http\Requests\MiBandeja\StoreGrupoFirmanteRequest $request, $grupoId)
    {
        try {
            $grupo = \App\Models\MiBandeja\MiBandejaTemp::find($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            if ($grupo->firmantes()->where('user_id', $request->user_id)->exists()) {
                return $this->errorResponse('El usuario ya es firmante en este grupo', null, 422);
            }

            $firmante = \App\Models\MiBandeja\MiBandejaTempGrupoFirmante::create([
                'grupo_id' => $grupoId,
                'user_id' => $request->user_id,
                'cargo_id' => $request->cargo_id,
                'orden_firma' => $request->integer('orden_firma', 1),
            ]);

            $firmante->load(['user.cargo', 'cargo']);

            return $this->successResponse($firmante, 'Firmante agregado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al agregar firmante', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un firmante existente en un grupo colaborativo temporal.
     *
     * @param Request $request Solicitud HTTP con datos a actualizar
     * @param int $grupoId Identificador del grupo
     * @param int $id Identificador del firmante
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el firmante actualizado
     */
    public function update(Request $request, $grupoId, $id)
    {
        try {
            $firmante = \App\Models\MiBandeja\MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

            if (! $firmante) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $firmante->update($request->only(['cargo_id', 'orden_firma', 'subio_plantilla', 'descargo_plantilla', 'fechor_terminado', 'fechor_firmado']));

            $firmante->load(['user.cargo', 'cargo']);

            return $this->successResponse($firmante, 'Firmante actualizado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar firmante', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un firmante de un grupo colaborativo temporal.
     *
     * @param int $grupoId Identificador del grupo
     * @param int $id Identificador del firmante a eliminar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con resultado de la operación
     */
    public function destroy($grupoId, $id)
    {
        try {
            $firmante = \App\Models\MiBandeja\MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

            if (! $firmante) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $firmante->delete();

            return $this->successResponse(null, 'Firmante eliminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar firmante', $e->getMessage(), 500);
        }
    }

    /**
     * Marca un firmante como terminado en un grupo colaborativo temporal.
     *
     * @param int $grupoId Identificador del grupo
     * @param int $id Identificador del firmante a marcar como terminado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el firmante actualizado
     */
    public function marcarTerminado($grupoId, $id)
    {
        try {
            $firmante = \App\Models\MiBandeja\MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

            if (! $firmante) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $firmante->update([
                'estado_tarea' => 'cumplido',
                'fechor_terminado' => now(),
                'descargo_plantilla' => true,
            ]);

            return $this->successResponse($firmante, 'Firmante marcado como terminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al marcar como terminado', $e->getMessage(), 500);
        }
    }

    /**
     * Registra la firma de un firmante en un grupo colaborativo temporal.
     *
     * @param int $grupoId Identificador del grupo
     * @param int $id Identificador del firmante
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el firmante actualizado
     */
    public function firmar($grupoId, $id)
    {
        try {
            $firmante = \App\Models\MiBandeja\MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

            if (! $firmante) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $firmante->update([
                'fechor_firmado' => now(),
            ]);

            return $this->successResponse($firmante, 'Firma registrada');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al registrar firma', $e->getMessage(), 500);
        }
    }
}