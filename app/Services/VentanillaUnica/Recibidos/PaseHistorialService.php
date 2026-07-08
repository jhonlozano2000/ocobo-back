<?php

namespace App\Services\VentanillaUnica\Recibidos;

use App\Models\ControlAcceso\UserCargo;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciPaseHistorial;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciResponsable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PaseHistorialService
{
    /**
     * Registra un pase y crea el nuevo responsable activo.
     *
     * El usuario anterior sigue activo en ventanilla_radica_reci_responsa
     * para preservar el historial (decisión confirmada con el usuario).
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

            $historial = VentanillaRadicaReciPaseHistorial::create([
                'radica_reci_id' => $data['radica_reci_id'],
                'usuario_origen_id' => $usuarioOrigenId,
                'users_cargos_destino_id' => $usersCargosId,
                'usuario_destino_id' => $userCargo->user_id,
                'tipo' => $data['tipo'] ?? VentanillaRadicaReciPaseHistorial::TIPO_PASE,
            ]);

            $responsable = VentanillaRadicaReciResponsable::create([
                'radica_reci_id' => $data['radica_reci_id'],
                'users_cargos_id' => $usersCargosId,
                'custodio' => 0,
            ]);

            DB::commit();

            $radicado = VentanillaRadicaReci::find($data['radica_reci_id']);
            if ($radicado) {
                $radicado->actualizarEstadoTrabajo();
            }

            return [
                'historial' => $historial->load(['usuarioOrigen', 'usuarioDestino', 'usersCargosDestino.cargo']),
                'responsable' => $responsable->load(['usuarioCargo', 'radicado']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}