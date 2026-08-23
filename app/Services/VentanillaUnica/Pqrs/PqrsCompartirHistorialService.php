<?php

namespace App\Services\VentanillaUnica\Pqrs;

use App\Models\ControlAcceso\UserCargo;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsCompartirHistorial;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsResponsable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Servicio PqrsCompartirHistorialService - Lógica de negocio para compartidos (CC) PQRS
 *
 * Encapsula la lógica de registrar un compartido (copia de conocimiento) de un PQRS.
 * A diferencia del pase, NO transfiere responsabilidad principal, pero SÍ crea un
 * responsable (no custodio) para dar acceso de lectura al destinatario.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */
class PqrsCompartirHistorialService
{
    /**
     * Registra un nuevo compartido (CC) de un PQRS.
     *
     * Realiza las siguientes operaciones en transacción:
     * 1. Valida/resuelve el UserCargo destino
     * 2. Crea registro inmutable en VentanillaPqrsCompartirHistorial
     * 3. Crea responsable (no custodio) en VentanillaPqrsResponsable para acceso de lectura
     * 4. Actualiza estado de trabajo del PQRS
     *
     * @param array $data Datos del compartido:
     *   - pqrs_id (int, requerido): ID del PQRS
     *   - users_cargos_destino_id (int, opcional si hay usuario_destino_id): ID del UserCargo
     *   - usuario_destino_id (int, opcional si hay users_cargos_destino_id): ID del usuario
     *   - usuario_origen_id (int, opcional): Usuario que comparte (default: auth()->id())
     *
     * @return array{
     *   historial: VentanillaPqrsCompartirHistorial,
     *   responsable: VentanillaPqrsResponsable
     * } Registros creados con relaciones cargadas
     *
     * @throws \Exception Si no se encuentra cargo/usuario destino válido
     */
    public function registrarCompartir(array $data): array
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

            // 1. Crear historial inmutable del compartido
            $historial = VentanillaPqrsCompartirHistorial::create([
                'pqrs_id' => $data['pqrs_id'],
                'usuario_origen_id' => $usuarioOrigenId,
                'users_cargos_destino_id' => $usersCargosId,
                'usuario_destino_id' => $userCargo->user_id,
            ]);

            // 2. Crear/obtener responsable de lectura (evita duplicados por pqrs+userCargo)
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