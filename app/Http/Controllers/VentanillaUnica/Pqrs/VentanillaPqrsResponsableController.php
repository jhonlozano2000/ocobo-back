<?php

namespace App\Http\Controllers\VentanillaUnica\Pqrs;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ControlAcceso\UserCargo;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsResponsable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Controlador VentanillaPqrsResponsableController - Gestión de responsables de PQRS
 *
 * Maneja la asignación, actualización, eliminación y consulta de responsables
 * (usuarios con cargo) asignados a radicados PQRS. Incluye funcionalidad de
 * custodia (responsable principal), acuse digital (marcar como visto) y
 * notificaciones in-app automáticas al asignar.
 *
 * Permisos requeridos: 'Radicar -> PQRSF -> Editar'
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */
class VentanillaPqrsResponsableController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> PQRSF -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Editar')->only([
            'index', 'store', 'show', 'update', 'destroy',
            'getByPqrs', 'assignToPqrs', 'marcarVisto'
        ]);
    }

    /**
     * Lista responsables con filtros opcionales y paginación.
     *
     * @param Request $request
     *   - pqrs_id (int, opcional): Filtrar por PQRS
     *   - users_cargos_id (int, opcional): Filtrar por usuario-cargo
     *   - per_page (int, opcional): Items por página (paginado)
     * @return JsonResponse Listado de responsables con relaciones userCargo y pqrs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = VentanillaPqrsResponsable::with(['userCargo', 'pqrs']);

            if ($request->filled('pqrs_id')) {
                $query->where('pqrs_id', $request->pqrs_id);
            }

            if ($request->filled('users_cargos_id')) {
                $query->where('users_cargos_id', $request->users_cargos_id);
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
            return $this->errorResponse('Error al obtener el listado de responsables', $e->getMessage(), 500);
        }
    }

    /**
     * Asigna múltiples responsables a uno o varios PQRS (batch).
     * Crea notificaciones in-app para cada usuario asignado.
     *
     * @param Request $request
     *   - responsables (array, requerido): Array de objetos con:
     *     - pqrs_id (int, requerido): ID del PQRS
     *     - users_cargos_id (int, requerido): ID del UserCargo
     *     - custodio (bool, opcional): Si es custodio principal
     * @return JsonResponse Responsables creados con relaciones cargadas (201)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'responsables' => 'required|array|min:1',
                'responsables.*.pqrs_id' => 'required|integer|exists:ventanilla_pqrs,id',
                'responsables.*.users_cargos_id' => 'required|integer|exists:users_cargos,id',
                'responsables.*.custodio' => 'boolean',
            ]);

            $responsables = $validatedData['responsables'];
            $responsablesCreados = [];

            foreach ($responsables as $responsableData) {
                $data = [
                    'pqrs_id' => (int) $responsableData['pqrs_id'],
                    'users_cargos_id' => (int) $responsableData['users_cargos_id'],
                    'custodio' => filter_var($responsableData['custodio'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];

                $responsable = VentanillaPqrsResponsable::create($data);
                $responsablesCreados[] = $responsable->load(['userCargo', 'pqrs']);

                // Crear notificación in-app para el usuario responsable
                $userCargo = UserCargo::find($data['users_cargos_id']);
                if ($userCargo && $userCargo->user) {
                    $pqrs = VentanillaPqrs::find($data['pqrs_id']);
                    $titulo = $responsableData['custodio'] ? 'Nuevo PQRS asignado (custodio)' : 'Nuevo PQRS asignado';
                    $mensaje = $pqrs
                        ? 'Se le ha asignado el PQRS ' . $pqrs->radicado?->num_radicado . ' como responsable.'
                        : 'Se le ha asignado un nuevo PQRS como responsable.';

                    \App\Models\Notificacion::create([
                        'user_id' => $userCargo->user_id,
                        'type' => 'asignacion_responsable_pqrs',
                        'title' => $titulo,
                        'message' => $mensaje,
                        'notifiable_type' => VentanillaPqrs::class,
                        'notifiable_id' => $data['pqrs_id'],
                        'data' => [
                            'pqrs_id' => $data['pqrs_id'],
                            'num_radicado' => $pqrs?->radicado?->num_radicado,
                            'custodio' => $responsableData['custodio'],
                            'asignado_por' => auth()->id(),
                        ],
                    ]);
                }
            }

            DB::commit();

            return $this->successResponse($responsablesCreados, 'Responsables asignados exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al asignar responsables', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un responsable específico por ID.
     *
     * @param int $id ID del responsable
     * @return JsonResponse Responsable con relaciones userCargo y pqrs
     */
    public function show($id): JsonResponse
    {
        try {
            $responsable = VentanillaPqrsResponsable::with(['userCargo', 'pqrs'])->find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            return $this->successResponse($responsable, 'Responsable encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un responsable (principalmente cambiar custodio).
     *
     * @param int $id ID del responsable
     * @param Request $request
     *   - custodio (bool, opcional): Nuevo valor de custodio
     * @return JsonResponse Responsable actualizado con relaciones
     */
    public function update($id, Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $responsable = VentanillaPqrsResponsable::find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $validatedData = $request->validate([
                'custodio' => 'boolean',
            ]);

            if (isset($validatedData['custodio'])) {
                $validatedData['custodio'] = filter_var($validatedData['custodio'], FILTER_VALIDATE_BOOLEAN);
            }

            $responsable->update($validatedData);

            DB::commit();

            return $this->successResponse(
                $responsable->load(['userCargo', 'pqrs']),
                'Responsable actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al actualizar el responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un responsable.
     *
     * @param int $id ID del responsable
     * @return JsonResponse Confirmación de eliminación
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $responsable = VentanillaPqrsResponsable::find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $responsable->delete();

            DB::commit();

            return $this->successResponse(null, 'Responsable eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al eliminar el responsable', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene todos los responsables de un PQRS específico.
     *
     * @param int $pqrs_id ID del PQRS
     * @return JsonResponse Colección de responsables con userCargo
     */
    public function getByPqrs($pqrs_id): JsonResponse
    {
        try {
            $responsables = VentanillaPqrsResponsable::with('userCargo')
                ->where('pqrs_id', $pqrs_id)
                ->get();

            if ($responsables->isEmpty()) {
                return $this->errorResponse('No hay responsables asignados para este PQRS', null, 404);
            }

            return $this->successResponse($responsables, 'Responsables del PQRS obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los responsables', $e->getMessage(), 500);
        }
    }

    /**
     * Registra acuse digital (marca como visto) para un responsable.
     * Usa el método del modelo VentanillaPqrsResponsable::marcarComoVisto()
     *
     * @param int $id ID del responsable
     * @return JsonResponse Responsable actualizado con fechor_visto
     */
    public function marcarVisto($id): JsonResponse
    {
        try {
            $responsable = VentanillaPqrsResponsable::findOrFail($id);

            if (! $responsable->marcarComoVisto()) {
                return $this->successResponse(
                    $responsable->fresh()->load(['userCargo', 'pqrs']),
                    'El PQRS ya había sido marcado como visto anteriormente'
                );
            }

            return $this->successResponse(
                $responsable->fresh()->load(['userCargo', 'pqrs']),
                'Acuse digital registrado exitosamente'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Responsable no encontrado', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al registrar el acuse digital', $e->getMessage(), 500);
        }
    }

    /**
     * Asigna responsables a un PQRS específico (endpoint alternativo por PQRS).
     * Función similar a store() pero con pqrs_id en la URL.
     *
     * @param int $pqrs_id ID del PQRS
     * @param Request $request
     *   - responsables (array, requerido): Array de objetos con:
     *     - users_cargos_id (int, requerido): ID del UserCargo
     *     - custodio (bool, opcional): Si es custodio principal
     * @return JsonResponse Responsables creados con relaciones cargadas (201)
     */
    public function assignToPqrs($pqrs_id, Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'responsables' => 'required|array|min:1',
                'responsables.*.users_cargos_id' => 'required|integer|exists:users_cargos,id',
                'responsables.*.custodio' => 'boolean',
            ]);

            $responsables = $validatedData['responsables'];
            $responsablesCreados = [];

            foreach ($responsables as $responsableData) {
                $responsable = VentanillaPqrsResponsable::create([
                    'pqrs_id' => $pqrs_id,
                    'users_cargos_id' => $responsableData['users_cargos_id'],
                    'custodio' => $responsableData['custodio'],
                ]);
                $responsablesCreados[] = $responsable->load(['userCargo', 'pqrs']);

                // Crear notificación in-app para el usuario responsable
                $userCargo = UserCargo::find($responsableData['users_cargos_id']);
                if ($userCargo && $userCargo->user) {
                    $pqrs = VentanillaPqrs::find($pqrs_id);
                    $titulo = $responsableData['custodio'] ? 'Nuevo PQRS asignado (custodio)' : 'Nuevo PQRS asignado';
                    $mensaje = $pqrs
                        ? 'Se le ha asignado el PQRS ' . $pqrs->radicado?->num_radicado . ' como responsable.'
                        : 'Se le ha asignado un nuevo PQRS como responsable.';

                    \App\Models\Notificacion::create([
                        'user_id' => $userCargo->user_id,
                        'type' => 'asignacion_responsable_pqrs',
                        'title' => $titulo,
                        'message' => $mensaje,
                        'notifiable_type' => VentanillaPqrs::class,
                        'notifiable_id' => $pqrs_id,
                        'data' => [
                            'pqrs_id' => $pqrs_id,
                            'num_radicado' => $pqrs?->radicado?->num_radicado,
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
            DB::rollBack();

            return $this->errorResponse('Error al asignar responsables', $e->getMessage(), 500);
        }
    }
}