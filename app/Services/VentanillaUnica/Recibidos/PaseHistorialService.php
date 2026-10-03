<?php

namespace App\Services\VentanillaUnica\Recibidos;

use App\Models\ControlAcceso\UserCargo;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciPaseHistorial;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciResponsable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaseHistorialService
{
    /**
     * Registra un pase y transfiere la responsabilidad:
     * - El usuario destino se asigna como nuevo responsable activo.
     * - Si el usuario origen era custodio, el destino hereda el rol de custodio.
     * - El usuario origen se retira de la responsabilidad activa (su bandeja se actualiza),
     *   quedando su participación registrada de forma permanente e inmutable en el historial.
     */
    public function registrarPase(array $data): array
    {
        DB::beginTransaction();
        try {
            $usersCargosId = $data['users_cargos_destino_id'] ?? null;

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

            // Validación: No transferir a sí mismo
            if ((int) $usuarioOrigenId === (int) $userCargo->user_id) {
                throw new \Exception('No puedes realizar un pase hacia ti mismo.');
            }

            // Validación: No transferir a quien ya es responsable activo
            $yaEsResponsable = VentanillaRadicaReciResponsable::where('radica_reci_id', $data['radica_reci_id'])
                ->whereHas('userCargo', function ($q) use ($userCargo) {
                    $q->where('user_id', $userCargo->user_id);
                })
                ->exists();

            if ($yaEsResponsable) {
                throw new \Exception('El funcionario seleccionado ya es responsable de este radicado.');
            }

            // Buscar la asignación activa del usuario origen en este radicado
            $responsableOrigen = VentanillaRadicaReciResponsable::where('radica_reci_id', $data['radica_reci_id'])
                ->whereHas('userCargo', function ($q) use ($usuarioOrigenId) {
                    $q->where('user_id', $usuarioOrigenId);
                })
                ->first();

            // Heredar rol de custodio si el origen lo tenía
            $esCustodio = $responsableOrigen ? (bool) $responsableOrigen->custodio : false;

            // 1. Registrar en historial inmutable
            $historial = VentanillaRadicaReciPaseHistorial::create([
                'radica_reci_id' => $data['radica_reci_id'],
                'usuario_origen_id' => $usuarioOrigenId,
                'users_cargos_destino_id' => $usersCargosId,
                'usuario_destino_id' => $userCargo->user_id,
                'tipo' => $data['tipo'] ?? VentanillaRadicaReciPaseHistorial::TIPO_PASE,
            ]);

            // 2. Crear nuevo responsable activo
            $responsable = VentanillaRadicaReciResponsable::create([
                'radica_reci_id' => $data['radica_reci_id'],
                'users_cargos_id' => $usersCargosId,
                'custodio' => $esCustodio ? 1 : 0,
            ]);

            // 3. Desvincular al usuario origen de la responsabilidad activa
            if ($responsableOrigen) {
                $responsableOrigen->delete();
            }

            DB::commit();

            $radicado = VentanillaRadicaReci::find($data['radica_reci_id']);
            if ($radicado) {
                $radicado->actualizarEstadoTrabajo();
            }

            return [
                'historial' => $historial->load(['usuarioOrigen', 'usuarioDestino', 'usersCargosDestino.cargo']),
                'responsable' => $responsable->load(['userCargo.user', 'userCargo.cargo', 'radicado']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
