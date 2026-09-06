<?php

namespace App\Http\Controllers\VentanillaUnica\Recibidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\Generales\ListResponsablesRequest;
use App\Http\Requests\Ventanilla\Recibidos\StoreResponsableReciboRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\NotificationPreferenceTrait;
use App\Models\ControlAcceso\UserCargo;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciResponsable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class VentanillaRadicaReciResponsableController extends Controller
{
    use ApiResponseTrait, NotificationPreferenceTrait;

    private const PERM = 'Radicar -> Cores. Recibida -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Editar')->only(['index', 'store', 'show', 'update', 'destroy', 'getByRadicado', 'assignToRadicado']);
    }

    public function index(ListResponsablesRequest $request)
    {
        try {
            $query = VentanillaRadicaReciResponsable::with(['usuarioCargo', 'radicado']);

            if ($request->filled('radica_reci_id')) {
                $query->where('radica_reci_id', $request->radica_reci_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $query->orderBy('created_at', 'desc');

            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $responsables = $query->paginate($perPage);
            } else {
                $responsables = $query->get();
            }

            return $this->successResponse($responsables, 'Listado de responsables obtenido exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener el listado de responsables', $e->getMessage(), 500);
        }
    }

    public function store(StoreResponsableReciboRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            if (! isset($validatedData['responsables']) || ! is_array($validatedData['responsables']) || empty($validatedData['responsables'])) {
                return $this->errorResponse('Se debe enviar un array de responsables no vacío', null, 400);
            }

            $responsables = $validatedData['responsables'];
            $responsablesCreados = [];
            $radicaReciId = null;

            foreach ($responsables as $responsableData) {
                $data = [
                    'radica_reci_id' => (int) $responsableData['radica_reci_id'],
                    'users_cargos_id' => (int) $responsableData['users_cargos_id'],
                    'custodio' => filter_var($responsableData['custodio'], FILTER_VALIDATE_BOOLEAN),
                ];

                $responsable = VentanillaRadicaReciResponsable::create($data);
                $responsablesCreados[] = $responsable->load(['usuarioCargo', 'radicado']);
                $radicaReciId = $data['radica_reci_id'];

                // Crear notificación in-app para el usuario responsable
                $userCargo = UserCargo::find($data['users_cargos_id']);
                if ($userCargo && $userCargo->user) {
                    $radicado = VentanillaRadicaReci::find($data['radica_reci_id']);
                    $titulo = $responsableData['custodio'] ? 'Nuevo radicado asignado (custodio)' : 'Nuevo radicado asignado';
                    $mensaje = $radicado
                        ? 'Se le ha asignado el radicado '.$radicado->num_radicado.' como responsable.'
                        : 'Se le ha asignado un nuevo radicado como responsable.';

                    $this->createNotificationIfAllowed([
                        'user_id' => $userCargo->user_id,
                        'type' => 'asignacion_responsable',
                        'title' => $titulo,
                        'message' => $mensaje,
                        'notifiable_type' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
                        'notifiable_id' => $data['radica_reci_id'],
                        'data' => [
                            'radica_reci_id' => $data['radica_reci_id'],
                            'num_radicado' => $radicado?->num_radicado,
                            'custodio' => $responsableData['custodio'],
                            'asignado_por' => auth()->id(),
                        ],
                    ]);
                }
            }

            DB::commit();

            if ($radicaReciId) {
                $radicado = VentanillaRadicaReci::find($radicaReciId);
                if ($radicado) {
                    $radicado->actualizarEstadoTrabajo();
                }
            }

            return $this->successResponse($responsablesCreados, 'Responsables asignados exitosamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            DB::rollBack();

            return $this->errorResponse('Error al asignar responsables', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $responsable = VentanillaRadicaReciResponsable::with(['usuarioCargo', 'radicado'])->find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            return $this->successResponse($responsable, 'Responsable encontrado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener el responsable', $e->getMessage(), 500);
        }
    }

    public function update($id, StoreResponsableReciboRequest $request)
    {
        try {
            DB::beginTransaction();

            $responsable = VentanillaRadicaReciResponsable::find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $validatedData = $request->validated();

            if (isset($validatedData['custodio'])) {
                $validatedData['custodio'] = filter_var($validatedData['custodio'], FILTER_VALIDATE_BOOLEAN);
            }

            $responsable->update($validatedData);

            DB::commit();

            return $this->successResponse(
                $responsable->load(['usuarioCargo', 'radicado']),
                'Responsable actualizado exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            DB::rollBack();

            return $this->errorResponse('Error al actualizar el responsable', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $responsable = VentanillaRadicaReciResponsable::find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $radicaReciId = $responsable->radica_reci_id;
            $responsable->delete();

            DB::commit();

            $radicado = VentanillaRadicaReci::find($radicaReciId);
            if ($radicado) {
                $radicado->actualizarEstadoTrabajo();
            }

            return $this->successResponse(null, 'Responsable eliminado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            DB::rollBack();

            return $this->errorResponse('Error al eliminar el responsable', $e->getMessage(), 500);
        }
    }

    public function getByRadicado($radica_reci_id)
    {
        try {
            $responsables = VentanillaRadicaReciResponsable::with('usuarioCargo')
                ->where('radica_reci_id', $radica_reci_id)
                ->get();

            if ($responsables->isEmpty()) {
                return $this->errorResponse('No hay responsables asignados para este radicado', null, 404);
            }

            return $this->successResponse($responsables, 'Responsables de la radicación obtenidos exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener los responsables', $e->getMessage(), 500);
        }
    }

    /**
     * Acuse digital de recibo: marca el responsable como "visto".
     */
    public function marcarVisto($id): JsonResponse
    {
        try {
            $responsable = VentanillaRadicaReciResponsable::with('usuarioCargo')->findOrFail($id);

            if ((int) ($responsable->usuarioCargo?->user_id) !== (int) auth()->id()) {
                return $this->errorResponse('Solo el responsable asignado puede registrar su acuse digital', null, 403);
            }

            if (! $responsable->marcarComoVisto()) {
                return $this->successResponse(
                    $responsable->fresh()->load(['usuarioCargo', 'radicado']),
                    'El radicado ya había sido marcado como visto anteriormente'
                );
            }

            return $this->successResponse(
                $responsable->fresh()->load(['usuarioCargo', 'radicado']),
                'Acuse digital registrado exitosamente'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Responsable no encontrado', null, 404);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al registrar el acuse digital', $e->getMessage(), 500);
        }
    }

    public function assignToRadicado($radica_reci_id, StoreResponsableReciboRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            $responsables = $validatedData['responsables'];
            $responsablesCreados = [];

            foreach ($responsables as $responsableData) {
                $responsable = VentanillaRadicaReciResponsable::create([
                    'radica_reci_id' => $radica_reci_id,
                    'users_cargos_id' => $responsableData['users_cargos_id'],
                    'custodio' => $responsableData['custodio'],
                ]);
                $responsablesCreados[] = $responsable->load(['usuarioCargo', 'radicado']);

                // Crear notificación in-app para el usuario responsable
                $userCargo = UserCargo::find($responsableData['users_cargos_id']);
                if ($userCargo && $userCargo->user) {
                    $radicado = VentanillaRadicaReci::find($radica_reci_id);
                    $titulo = $responsableData['custodio'] ? 'Nuevo radicado asignado (custodio)' : 'Nuevo radicado asignado';
                    $mensaje = $radicado
                        ? 'Se le ha asignado el radicado '.$radicado->num_radicado.' como responsable.'
                        : 'Se le ha asignado un nuevo radicado como responsable.';

                    $this->createNotificationIfAllowed([
                        'user_id' => $userCargo->user_id,
                        'type' => 'asignacion_responsable',
                        'title' => $titulo,
                        'message' => $mensaje,
                        'notifiable_type' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
                        'notifiable_id' => $radica_reci_id,
                        'data' => [
                            'radica_reci_id' => $radica_reci_id,
                            'num_radicado' => $radicado?->num_radicado,
                            'custodio' => $responsableData['custodio'],
                            'asignado_por' => auth()->id(),
                        ],
                    ]);
                }
            }

            DB::commit();

            return $this->successResponse($responsablesCreados, 'Responsables asignados exitosamente', 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            DB::rollBack();

            return $this->errorResponse('Error al asignar responsables', $e->getMessage(), 500);
        }
    }
}
