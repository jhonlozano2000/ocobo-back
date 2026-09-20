<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Events\NotificationPushed;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MiBandeja\Concerns\AutorizaGrupoColaborativo;
use App\Http\Requests\MiBandeja\StoreGrupoProyectorRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTempGrupoProyector;
use App\Models\Notificacion;
/**
 * Controlador para gestionar proyectores de grupos colaborativos temporales.
 * Permite agregar, actualizar, eliminar y marcar como terminado a los proyectores.
 */
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaTempGrupoProyectorController extends Controller
{
    use ApiResponseTrait;
    use AutorizaGrupoColaborativo;

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
     * @param  int  $grupoId  Identificador del grupo
     * @return JsonResponse Respuesta JSON con los proyectores del grupo
     */
    public function index($grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $proyectores = $grupo->proyectores()->with(['user.cargo', 'cargo'])->get();

            return $this->successResponse($proyectores, 'Proyectores del grupo');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener proyectores', $e->getMessage(), 500);
        }
    }

    /**
     * Agrega un nuevo proyector a un grupo colaborativo temporal.
     *
     * @param  StoreGrupoProyectorRequest  $request  Solicitud HTTP con datos del proyector
     * @param  int  $grupoId  Identificador del grupo
     * @return JsonResponse Respuesta JSON con el proyector creado
     */
    public function store(StoreGrupoProyectorRequest $request, $grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            if ($grupo->proyectores()->where('user_id', $request->user_id)->exists()) {
                return $this->errorResponse('El usuario ya es proyector en este grupo', null, 422);
            }

            $proyector = MiBandejaTempGrupoProyector::create([
                'grupo_id' => $grupoId,
                'user_id' => $request->user_id,
                'cargo_id' => $request->cargo_id,
            ]);

            $proyector->load(['user.cargo', 'cargo']);

            // Crear notificación in-app para el proyector
            $notificacion = Notificacion::create([
                'user_id' => $request->user_id,
                'type' => 'asignacion_proyector',
                'title' => 'Proyector asignado en grupo colaborativo',
                'message' => 'Se le ha asignado como proyector del grupo "'.$grupo->nombre.'".',
                'notifiable_type' => 'App\Models\MiBandeja\MiBandejaTemp',
                'notifiable_id' => $grupoId,
                'data' => [
                    'grupo_id' => $grupoId,
                    'nombre_grupo' => $grupo->nombre,
                    'asignado_por' => auth()->id(),
                ],
            ]);
            try {
                event(new NotificationPushed($notificacion));
            } catch (\Throwable $e) {
                \Log::warning('Broadcast NotificationPushed falló en proyector: '.$e->getMessage());
            }

            return $this->successResponse($proyector, 'Proyector agregado exitosamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al agregar proyector', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un proyector existente en un grupo colaborativo temporal.
     *
     * @param  Request  $request  Solicitud HTTP con datos a actualizar
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del proyector
     * @return JsonResponse Respuesta JSON con el proyector actualizado
     */
    public function update(Request $request, $grupoId, $id)
    {
        try {
            $proyector = MiBandejaTempGrupoProyector::where('grupo_id', $grupoId)->find($id);

            if (! $proyector) {
                return $this->errorResponse('Proyector no encontrado', null, 404);
            }

            $proyector->update($request->only(['cargo_id', 'subio_plantilla', 'descargo_plantilla', 'fechor_terminado']));

            $proyector->load(['user.cargo', 'cargo']);

            return $this->successResponse($proyector, 'Proyector actualizado');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al actualizar proyector', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un proyector de un grupo colaborativo temporal.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del proyector a eliminar
     * @return JsonResponse Respuesta JSON con resultado de la operación
     */
    public function destroy($grupoId, $id)
    {
        try {
            $proyector = MiBandejaTempGrupoProyector::where('grupo_id', $grupoId)->find($id);

            if (! $proyector) {
                return $this->errorResponse('Proyector no encontrado', null, 404);
            }

            $proyector->delete();

            return $this->successResponse(null, 'Proyector eliminado');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al eliminar proyector', $e->getMessage(), 500);
        }
    }

    /**
     * Marca un proyector como terminado en un grupo colaborativo temporal.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del proyector a marcar como terminado
     * @return JsonResponse Respuesta JSON con el proyector actualizado
     */
    public function marcarTerminado($grupoId, $id)
    {
        try {
            $proyector = MiBandejaTempGrupoProyector::where('grupo_id', $grupoId)->find($id);

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
