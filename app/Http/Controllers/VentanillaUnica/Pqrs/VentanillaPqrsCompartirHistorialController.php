<?php

namespace App\Http\Controllers\VentanillaUnica\Pqrs;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsCompartirHistorial;
use App\Services\VentanillaUnica\Pqrs\PqrsCompartirHistorialService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador VentanillaPqrsCompartirHistorialController - Historial de compartidos (CC) PQRS
 *
 * Gestiona el registro y consulta del historial inmutable de compartidos
 * (copias de conocimiento/CC) de radicados PQRS. A diferencia de los pases,
 * el compartido NO transfiere responsabilidad, solo da visibilidad/lectura.
 * La lógica de negocio se delega al servicio PqrsCompartirHistorialService.
 *
 * Permisos:
 * - Editar: 'Radicar -> PQRSF -> Editar' (para crear compartidos)
 * - Mostrar: 'Radicar -> PQRSF -> Mostrar' (para consultar historial)
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */
class VentanillaPqrsCompartirHistorialController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> PQRSF -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Editar')->only(['store']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['byPqrs']);
    }

    /**
     * Registra un nuevo compartido (CC) de un PQRS.
     * Delega la lógica al servicio PqrsCompartirHistorialService.
     *
     * @param int $pqrs_id ID del PQRS
     * @param PqrsCompartirHistorialService $service Servicio inyectado
     * @return JsonResponse Compartido creado con relaciones (201)
     *
     * Body request:
     *   - users_cargos_destino_id (int, requerido): ID del UserCargo destino
     *   - usuario_destino_id (int, requerido): ID del usuario destino
     */
    public function store($pqrs_id, PqrsCompartirHistorialService $service): JsonResponse
    {
        try {
            $data = request()->validate([
                'users_cargos_destino_id' => 'required|integer|exists:users_cargos,id',
                'usuario_destino_id' => 'required|integer|exists:users,id',
            ]);
            $data['pqrs_id'] = $pqrs_id;

            $result = $service->registrarCompartir($data);

            return $this->successResponse($result, 'PQRS compartido exitosamente', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $status = str_contains($e->getMessage(), 'no existe') ? 404 : 500;
            return $this->errorResponse('Error al compartir el PQRS', $e->getMessage(), $status);
        }
    }

    /**
     * Obtiene el historial completo de compartidos de un PQRS.
     * Ordenado descendente por fecha (más reciente primero).
     *
     * @param int $pqrs_id ID del PQRS
     * @return JsonResponse Colección de compartidos con relaciones:
     *   - usuarioOrigen: User que comparte
     *   - usuarioDestino: User destinatario
     *   - usersCargosDestino.cargo: Cargo del destinatario
     */
    public function byPqrs($pqrs_id): JsonResponse
    {
        try {
            $historial = VentanillaPqrsCompartirHistorial::with([
                'usuarioOrigen',
                'usuarioDestino',
                'usersCargosDestino.cargo'
            ])
                ->where('pqrs_id', $pqrs_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de compartir obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial de compartir', $e->getMessage(), 500);
        }
    }
}