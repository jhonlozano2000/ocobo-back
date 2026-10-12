<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MiBandeja\Concerns\AutorizaGrupoColaborativo;
use App\Http\Requests\MiBandeja\StoreGrupoAprobadorRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTempGrupoAprobador;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaTempGrupoAprobadorController extends Controller
{
    use ApiResponseTrait;
    use AutorizaGrupoColaborativo;

    private const PERM = 'Mi Bandeja - Grupos Colaborativos -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Gestionar Miembros')->only(['store', 'destroy', 'update']);
    }

    public function index($grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $aprobadores = $grupo->aprobadores()->with(['user.cargo', 'cargo'])->get();

            return $this->successResponse($aprobadores, 'Aprobadores del grupo');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener aprobadores', $e->getMessage(), 500);
        }
    }

    public function store(StoreGrupoAprobadorRequest $request, $grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            if ($grupo->aprobadores()->where('user_id', $request->user_id)->exists()) {
                return $this->errorResponse('El usuario ya es aprobador en este grupo', null, 422);
            }

            $aprobador = MiBandejaTempGrupoAprobador::create([
                'grupo_id' => $grupoId,
                'user_id' => $request->user_id,
                'cargo_id' => $request->cargo_id,
            ]);

            $aprobador->load(['user.cargo', 'cargo']);

            // Crear notificación in-app para el aprobador
            Notificacion::create([
                'user_id' => $request->user_id,
                'type' => 'asignacion_responsable',
                'title' => 'Aprobador asignado en grupo colaborativo',
                'message' => 'Se le ha asignado como aprobador del grupo "'.$grupo->nombre.'".',
                'notifiable_type' => 'App\Models\MiBandeja\MiBandejaTemp',
                'notifiable_id' => $grupoId,
                'data' => [
                    'grupo_id' => $grupoId,
                    'nombre_grupo' => $grupo->nombre,
                    'asignado_por' => auth()->id(),
                ],
            ]);

            return $this->successResponse($aprobador, 'Aprobador agregado exitosamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al agregar aprobador', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $grupoId, $id)
    {
        $this->autorizarGrupo($grupoId);

        // M07-01: el estado de la tarea solo cambia mediante marcarTerminado()
        $datos = $request->validate([
            'cargo_id' => ['sometimes', 'nullable', 'integer', 'exists:calidad_organigrama,id'],
        ]);

        try {
            $aprobador = MiBandejaTempGrupoAprobador::where('grupo_id', $grupoId)->find($id);

            if (! $aprobador) {
                return $this->errorResponse('Aprobador no encontrado', null, 404);
            }

            $aprobador->update($datos);

            $aprobador->load(['user.cargo', 'cargo']);

            return $this->successResponse($aprobador, 'Aprobador actualizado');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al actualizar aprobador', $e->getMessage(), 500);
        }
    }

    public function destroy($grupoId, $id)
    {
        $this->autorizarGrupo($grupoId);

        try {
            $aprobador = MiBandejaTempGrupoAprobador::where('grupo_id', $grupoId)->find($id);

            if (! $aprobador) {
                return $this->errorResponse('Aprobador no encontrado', null, 404);
            }

            $aprobador->delete();

            return $this->successResponse(null, 'Aprobador eliminado');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al eliminar aprobador', $e->getMessage(), 500);
        }
    }

    public function marcarTerminado($grupoId, $id)
    {
        $this->autorizarGrupo($grupoId);

        try {
            $aprobador = MiBandejaTempGrupoAprobador::where('grupo_id', $grupoId)->find($id);

            if (! $aprobador) {
                return $this->errorResponse('Aprobador no encontrado', null, 404);
            }

            if ((int) $aprobador->user_id !== (int) auth()->id()) {
                return $this->errorResponse('Solo el usuario titular puede marcar su tarea como terminada.', null, 403);
            }

            $aprobador->update([
                'estado_tarea' => 'cumplido',
                'fechor_terminado' => now(),
                'descargo_plantilla' => true,
            ]);

            return $this->successResponse($aprobador, 'Aprobador marcado como terminado');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al marcar como terminado', $e->getMessage(), 500);
        }
    }
}
