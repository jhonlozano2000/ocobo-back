<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Events\NotificationPushed;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MiBandeja\Concerns\AutorizaGrupoColaborativo;
use App\Http\Requests\MiBandeja\StoreGrupoFirmanteRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use App\Models\Notificacion;
/**
 * Controlador para gestionar firmantes de grupos colaborativos temporales.
 * Permite agregar, actualizar, eliminar, marcar como terminado y firmar documentos.
 */
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaTempGrupoFirmanteController extends Controller
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
     * Lista los firmantes de un grupo colaborativo temporal.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @return JsonResponse Respuesta JSON con los firmantes del grupo
     */
    public function index($grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $firmantes = $grupo->firmantes()->with(['user.cargo', 'cargo'])->get();

            return $this->successResponse($firmantes, 'Firmantes del grupo');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener firmantes', $e->getMessage(), 500);
        }
    }

    /**
     * Agrega un nuevo firmante a un grupo colaborativo temporal.
     *
     * @param  StoreGrupoFirmanteRequest  $request  Solicitud HTTP con datos del firmante
     * @param  int  $grupoId  Identificador del grupo
     * @return JsonResponse Respuesta JSON con el firmante creado
     */
    public function store(StoreGrupoFirmanteRequest $request, $grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            if ($grupo->firmantes()->where('user_id', $request->user_id)->exists()) {
                return $this->errorResponse('El usuario ya es firmante en este grupo', null, 422);
            }

            $firmante = MiBandejaTempGrupoFirmante::create([
                'grupo_id' => $grupoId,
                'user_id' => $request->user_id,
                'cargo_id' => $request->cargo_id,
                'orden_firma' => $request->integer('orden_firma', 1),
            ]);

            $firmante->load(['user.cargo', 'cargo']);

            // Crear notificación in-app para el firmante
            $notificacion = Notificacion::create([
                'user_id' => $request->user_id,
                'type' => 'asignacion_firmante',
                'title' => 'Firmante asignado en grupo colaborativo',
                'message' => 'Se le ha asignado como firmante del grupo "'.$grupo->nombre.'".',
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
                \Log::warning('Broadcast NotificationPushed falló en firmante: '.$e->getMessage());
            }

            return $this->successResponse($firmante, 'Firmante agregado exitosamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al agregar firmante', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un firmante existente en un grupo colaborativo temporal.
     *
     * @param  Request  $request  Solicitud HTTP con datos a actualizar
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del firmante
     * @return JsonResponse Respuesta JSON con el firmante actualizado
     */
    public function update(Request $request, $grupoId, $id)
    {
        try {
            $firmante = MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

            if (! $firmante) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $firmante->update($request->only(['cargo_id', 'orden_firma', 'subio_plantilla', 'descargo_plantilla', 'fechor_terminado', 'fechor_firmado']));

            $firmante->load(['user.cargo', 'cargo']);

            return $this->successResponse($firmante, 'Firmante actualizado');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al actualizar firmante', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un firmante de un grupo colaborativo temporal.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del firmante a eliminar
     * @return JsonResponse Respuesta JSON con resultado de la operación
     */
    public function destroy($grupoId, $id)
    {
        try {
            $firmante = MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

            if (! $firmante) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $firmante->delete();

            return $this->successResponse(null, 'Firmante eliminado');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al eliminar firmante', $e->getMessage(), 500);
        }
    }

    /**
     * Marca un firmante como terminado en un grupo colaborativo temporal.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del firmante a marcar como terminado
     * @return JsonResponse Respuesta JSON con el firmante actualizado
     */
    public function marcarTerminado($grupoId, $id)
    {
        try {
            $firmante = MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al marcar como terminado', $e->getMessage(), 500);
        }
    }

    /**
     * Registra la firma de un firmante en un grupo colaborativo temporal.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del firmante
     * @return JsonResponse Respuesta JSON con el firmante actualizado
     */
    public function firmar($grupoId, $id)
    {
        try {
            $firmante = MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

            if (! $firmante) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $firmante->update([
                'fechor_firmado' => now(),
            ]);

            return $this->successResponse($firmante, 'Firma registrada');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al registrar firma', $e->getMessage(), 500);
        }
    }
}
