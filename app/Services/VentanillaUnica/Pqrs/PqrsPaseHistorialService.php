<?php

namespace App\Services\VentanillaUnica\Pqrs;

use App\Models\ControlAcceso\UserCargo;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsPaseHistorial;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsResponsable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Servicio PqrsPaseHistorialService - Lógica de negocio para pases/reasignaciones PQRS
 *
 * Encapsula la lógica compleja de registrar un pase (traslado de responsabilidad)
 * de un radicado PQRS. Maneja validaciones de existencia de cargo/usuario,
 * creación del historial inmutable y actualización del responsable actual.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */
class PqrsPaseHistorialService
{
    /**
     * Registra un nuevo pase/asignación/reasignación de un PQRS.
     *
     * Realiza las siguientes operaciones en transacción:
     * 1. Valida/resuelve el UserCargo destino (desde users_cargos_destino_id o usuario_destino_id)
     * 2. Crea registro inmutable en VentanillaPqrsPaseHistorial
     * 3. Crea/actualiza responsable actual en VentanillaPqrsResponsable (no custodio)
     * 4. Actualiza estado de trabajo del PQRS (actualizarEstadoTrabajo)
     *
     * @param array $data Datos del pase:
     *   - pqrs_id (int, requerido): ID del PQRS
     *   - users_cargos_destino_id (int, opcional si hay usuario_destino_id): ID del UserCargo destino
     *   - usuario_destino_id (int, opcional si hay users_cargos_destino_id): ID del usuario destino
     *   - tipo (string, opcional): 'pase' | 'asignacion_inicial' | 'reasignacion' (default: 'pase')
     *   - usuario_origen_id (int, opcional): Usuario que origina (default: auth()->id())
     *
     * @return array{
     *   historial: VentanillaPqrsPaseHistorial,
     *   responsable: VentanillaPqrsResponsable
     * } Registros creados con relaciones cargadas
     *
     * @throws \Exception Si no se encuentra cargo/usuario destino válido
     */
    public function registrarPase(array $data): array
    {
        DB::beginTransaction();
        try {
            $pqrs = VentanillaPqrs::find($data['pqrs_id']);
            if (! $pqrs) {
                throw new \Exception('El PQRS no existe.');
            }

            $usersCargosId = $data['users_cargos_destino_id'] ?? null;

            // Si no viene cargo destino pero sí usuario, buscar su cargo activo
            if (! $usersCargosId && ! empty($data['usuario_destino_id'])) {
                $userCargo = UserCargo::cargoActivoDelUsuario((int) $data['usuario_destino_id']);
                if (! $userCargo) {
                    throw new \Exception('El usuario destino no tiene un cargo activo asignado.');
                }
                $usersCargosId = $userCargo->id;
            }

            if (! $usersCargosId) {
                throw new \Exception('Debe proporcionar el usuario destino o el cargo del usuario destino.');
            }

            $userCargo = UserCargo::with(['user', 'cargo'])->find($usersCargosId);
            if (! $userCargo) {
                throw new \Exception('El cargo del usuario destino no existe.');
            }

            if (! $userCargo->user) {
                throw new \Exception('El cargo seleccionado no tiene un usuario asociado.');
            }

            $usuarioOrigenId = $data['usuario_origen_id'] ?? Auth::id();

            // 1. Crear historial inmutable del pase
            $historial = VentanillaPqrsPaseHistorial::create([
                'pqrs_id' => $data['pqrs_id'],
                'usuario_origen_id' => $usuarioOrigenId,
                'users_cargos_destino_id' => $usersCargosId,
                'usuario_destino_id' => $userCargo->user_id,
                'tipo' => $data['tipo'] ?? VentanillaPqrsPaseHistorial::TIPO_PASE,
            ]);

            // 2. Crear/obtener responsable actual (evita duplicados por pqrs+userCargo)
            $responsable = VentanillaPqrsResponsable::firstOrCreate([
                'pqrs_id' => $data['pqrs_id'],
                'users_cargos_id' => $usersCargosId,
            ], [
                'custodio' => 0,
            ]);

            // 3. Actualizar estado de trabajo del PQRS (dentro de la transacción)
            $pqrs->actualizarEstadoTrabajo();

            DB::commit();

            return [
                'historial' => $historial->load(['usuarioOrigen', 'usuarioDestino', 'usersCargosDestino.cargo']),
                'responsable' => $responsable->load(['userCargo', 'pqrs']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}