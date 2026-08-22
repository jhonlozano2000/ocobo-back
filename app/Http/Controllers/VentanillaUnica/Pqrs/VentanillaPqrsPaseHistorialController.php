<?php

namespace App\Http\Controllers\VentanillaUnica\Pqrs;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsPaseHistorial;
use App\Services\VentanillaUnica\Pqrs\PqrsPaseHistorialService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador VentanillaPqrsPaseHistorialController - Historial de pases/reasignaciones PQRS
 *
 * Gestiona el registro y consulta del historial inmutable de movimientos (pases,
 * asignaciones iniciales, reasignaciones) de radicados PQRS entre usuarios/cargos.
 * La lógica de negocio (validaciones, actualización de responsable actual, etc.)
 * se delega al servicio PqrsPaseHistorialService.
 *
 * Permisos:
 * - Editar: 'Radicar -> PQRSF -> Editar' (para crear pases)
 * - Mostrar: 'Radicar -> PQRSF -> Mostrar' (para consultar historial)
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */
class VentanillaPqrsPaseHistorialController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> PQRSF -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Editar')->only(['store']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['byPqrs']);
    }

    /**
     * Registra un nuevo pase/reasignación/asignación inicial.
     * Delega la lógica al servicio PqrsPaseHistorialService.
     *
     * @param int $pqrs_id ID del PQRS
     * @param PqrsPaseHistorialService $service Servicio inyectado para lógica de negocio
     * @return JsonResponse Pase creado con relaciones (201)
     *
     * Body request:
     *   - users_cargos_destino_id (int, requerido): ID del UserCargo destino
     *   - usuario_destino_id (int, requerido): ID del usuario destino
     *   - tipo (string, opcional): 'pase' | 'asignacion_inicial' | 'reasignacion' (default: 'pase')
     */
    public function store($pqrs_id, PqrsPaseHistorialService $service): JsonResponse
    {
        try {
            $data = request()->validate([
                'users_cargos_destino_id' => 'required|integer|exists:users_cargos,id',
                'usuario_destino_id' => 'required|integer|exists:users,id',
                'tipo' => 'nullable|in:pase,asignacion_inicial,reasignacion',
            ]);
            $data['pqrs_id'] = $pqrs_id;

            $result = $service->registrarPase($data);

            return $this->successResponse($result, 'Pase registrado exitosamente', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $status = str_contains($e->getMessage(), 'no existe') ? 404 : 500;
            return $this->errorResponse('Error al registrar el pase', $e->getMessage(), $status);
        }
    }

    /**
     * Obtiene el historial completo de pases de un PQRS.
     * Ordenado descendente por fecha (más reciente primero).
     *
     * @param int $pqrs_id ID del PQRS
     * @return JsonResponse Colección de pases con relaciones:
     *   - usuarioOrigen: User que origina el pase
     *   - usuarioDestino: User destino
     *   - usersCargosDestino.cargo: Cargo del destino
     */
    public function byPqrs($pqrs_id): JsonResponse
    {
        try {
            $historial = VentanillaPqrsPaseHistorial::with([
                'usuarioOrigen',
                'usuarioDestino',
                'usersCargosDestino.cargo'
            ])
                ->where('pqrs_id', $pqrs_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de pases obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial de pases', $e->getMessage(), 500);
        }
    }
}