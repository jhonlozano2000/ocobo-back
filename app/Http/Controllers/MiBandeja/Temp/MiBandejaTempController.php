<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\CheckInRequest;
use App\Http\Requests\MiBandeja\StoreMiBandejaTempRequest;
use App\Http\Requests\MiBandeja\UpdateMiBandejaTempRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Rules\MagicMime;
use App\Services\MiBandeja\GrupoColaborativoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador para gestionar grupos colaborativos temporales en Mi Bandeja.
 * Proporciona operaciones CRUD y envío a trámite de grupos.
 */
class MiBandejaTempController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Mi Bandeja - Grupos Colaborativos -> ';

    public function __construct(
        private readonly GrupoColaborativoService $grupoService,
    ) {
        $this->middleware('can:'.self::PERM.'Ver')->only(['index', 'show']);
        $this->middleware('can:'.self::PERM.'Crear')->only(['store']);
        $this->middleware('can:'.self::PERM.'Editar')->only(['update']);
        $this->middleware('can:'.self::PERM.'Eliminar')->only(['destroy']);
        $this->middleware('can:'.self::PERM.'Enviar Tramite')->only(['enviarTramite']);
    }

    /**
     * Lista grupos colaborativos temporales del usuario autenticado.
     *
     * @param Request $request Solicitud HTTP con parámetros de búsqueda y filtro
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los grupos encontrados
     */
    public function index(Request $request)
    {
        try {
            $userId = Auth::id();
            $search = $request->get('search', '');
            $estadoGrupo = $request->get('estado_grupo', '');

            $query = MiBandejaTemp::with([
                'responsables.user.cargo',
                'firmantes.user.cargo',
                'proyectores.user.cargo',
                'adjuntos',
            ])
            ->whereHas('responsables', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->orWhereHas('firmantes', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->orWhereHas('proyectores', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('asunto', 'like', "%{$search}%");
                });
            }

            if ($estadoGrupo) {
                $query->where('estado_grupo', $estadoGrupo);
            }

            $grupos = $query->limit(50)->get();

            return $this->successResponse($grupos, 'Grupos colaborativos obtenidos');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener grupos', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo grupo colaborativo temporal.
     *
     * @param StoreMiBandejaTempRequest $request Solicitud HTTP con datos validados del grupo
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el grupo creado
     */
    public function store(StoreMiBandejaTempRequest $request)
    {
        try {
            $validated = $request->validated();
            $validated['usua_crea_id'] = Auth::id();
            $validated['estado'] = 'borrador';
            $validated['estado_grupo'] = 'activo';

            $grupo = MiBandejaTemp::create($validated);

            return $this->successResponse(
                $grupo->load(['responsables.user.cargo', 'firmantes.user.cargo', 'proyectores.user.cargo']),
                'Grupo colaborativo creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear grupo', $e->getMessage(), 500);
        }
    }

    /**
     * Muestra el detalle de un grupo colaborativo temporal específico.
     *
     * @param int $id Identificador del grupo
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el grupo encontrado
     */
    public function show($id)
    {
        try {
            $grupo = MiBandejaTemp::with([
                'responsables.user.cargo',
                'firmantes.user.cargo',
                'proyectores.user.cargo',
                'adjuntos',
                'creadoPor',
            ])->find($id);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            return $this->successResponse($grupo, 'Detalle del grupo');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener grupo', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un grupo colaborativo temporal existente.
     *
     * @param UpdateMiBandejaTempRequest $request Solicitud HTTP con datos actualizados
     * @param int $id Identificador del grupo a actualizar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el grupo actualizado
     */
    public function update(UpdateMiBandejaTempRequest $request, $id)
    {
        try {
            $grupo = MiBandejaTemp::find($id);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $grupo->update($request->validated());

            return $this->successResponse(
                $grupo->load(['responsables.user.cargo', 'firmantes.user.cargo', 'proyectores.user.cargo', 'adjuntos']),
                'Grupo actualizado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar grupo', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un grupo colaborativo temporal (solo si no está activo).
     *
     * @param int $id Identificador del grupo a eliminar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con resultado de la operación
     */
    public function destroy($id)
    {
        try {
            $grupo = MiBandejaTemp::find($id);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            if ($grupo->estado_grupo === 'activo') {
                return $this->errorResponse('No se puede eliminar un grupo activo. Anúlelo primero.', null, 422);
            }

            $grupo->delete();

            return $this->successResponse(null, 'Grupo eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar grupo', $e->getMessage(), 500);
        }
    }

    /**
     * Descarga el documento del grupo y lo bloquea (check-out).
     */
    public function checkOut($id)
    {
        try {
            $grupo = MiBandejaTemp::find($id);

            if (!$grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $resultado = $this->grupoService->checkOut($grupo, Auth::user());

            return response()->download(
                $resultado['archivo'],
                $resultado['nombre'],
                ['Content-Type' => $resultado['mime']]
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar documento', $e->getMessage(), 500);
        }
    }

    /**
     * Sube una nueva versión del documento y libera el bloqueo (check-in).
     */
    public function checkIn(CheckInRequest $request, $id)
    {
        try {
            $grupo = MiBandejaTemp::find($id);

            if (!$grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $version = $this->grupoService->checkIn(
                $grupo,
                Auth::user(),
                $request->file('archivo'),
                $request->input('comentario')
            );

            $version->load('subidoPor');

            return $this->successResponse($version, 'Documento actualizado exitosamente');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al subir documento', $e->getMessage(), 500);
        }
    }

    /**
     * Sube la versión inicial del documento (primera carga).
     */
    public function subirVersionInicial(Request $request, $id)
    {
        try {
            $request->validate([
                'archivo' => ['required', 'file', 'mimes:docx,doc,pdf,odt,dotx,xlsx,xls,pptx,ppt,txt,csv,jpeg,jpg,png,gif', 'max:51200', new MagicMime],
            ]);

            $grupo = MiBandejaTemp::find($id);

            if (!$grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            if ($grupo->versiones()->count() > 0) {
                return $this->errorResponse('El grupo ya tiene un documento. Use check-in para actualizar.', null, 422);
            }

            $version = $this->grupoService->subirVersionInicial(
                $grupo,
                Auth::user(),
                $request->file('archivo')
            );

            $grupo->update([
                'plantilla_cargada' => true,
                'estado' => 'en_curso',
            ]);

            $version->load('subidoPor');

            return $this->successResponse($version, 'Documento subido exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al subir documento', $e->getMessage(), 500);
        }
    }

    /**
     * Envía un grupo colaborativo temporal a trámite.
     * Verifica que todos los miembros hayan terminado su trabajo.
     *
     * @param int $id Identificador del grupo a enviar a trámite
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el grupo actualizado
     */
    public function enviarTramite(Request $request, $id)
    {
        try {
            $grupo = MiBandejaTemp::with([
                'responsables', 'firmantes', 'proyectores', 'adjuntos'
            ])->find($id);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $grupoActualizado = $this->grupoService->enviarTramite(
                $grupo,
                $request->input('respuesta_final')
            );

            return $this->successResponse($grupoActualizado, 'Grupo enviado a trámite exitosamente');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al enviar a trámite', $e->getMessage(), 500);
        }
    }

    /**
     * Anula un grupo colaborativo. Solo el creador puede hacerlo.
     */
    public function anular($id)
    {
        try {
            $grupo = MiBandejaTemp::find($id);

            if (!$grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $this->grupoService->anular($grupo, Auth::user());

            return $this->successResponse(null, 'Grupo anulado exitosamente');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 403);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al anular grupo', $e->getMessage(), 500);
        }
    }

    /**
     * Marca la tarea del usuario autenticado como terminada en un grupo.
     */
    public function marcarTerminado(Request $request, $id)
    {
        try {
            $grupo = MiBandejaTemp::find($id);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $resultado = $this->grupoService->marcarCumplido($grupo, Auth::user());

            $grupo->load(['responsables', 'firmantes', 'proyectores']);

            $responseData = [
                'grupo' => $grupo,
                'rol' => $resultado['rol'],
                'todos_cumplidos' => $resultado['todos_cumplidos'],
                'nuevo_estado' => $resultado['nuevo_estado'],
            ];

            return $this->successResponse($responseData, 'Tarea marcada como terminada');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), null, 403);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al marcar como terminado', $e->getMessage(), 500);
        }
    }
}