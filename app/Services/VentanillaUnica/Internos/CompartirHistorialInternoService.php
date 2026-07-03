<?php

namespace App\Services\VentanillaUnica\Internos;

use App\Models\ControlAcceso\UserCargo;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoCompartirHistorial;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoResponsa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CompartirHistorialInternoService
{
    /**
     * Registra un compartir (con copia / CC) y crea el nuevo responsable activo.
     *
     * Tabla separada de PaseHistorial. Crea un nuevo registro en
     * ventanilla_radica_interno_responsa y un registro inmutable en
     * ventanilla_radica_interno_compartir_historial.
     */
    public function registrarCompartir(array $data): array
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

            $historial = VentanillaRadicaInternoCompartirHistorial::create([
                'radica_interno_id' => $data['radica_interno_id'],
                'usuario_origen_id' => $usuarioOrigenId,
                'users_cargos_destino_id' => $usersCargosId,
                'usuario_destino_id' => $userCargo->user_id,
            ]);

            $responsable = VentanillaRadicaInternoResponsa::create([
                'radica_interno_id' => $data['radica_interno_id'],
                'users_cargos_id' => $usersCargosId,
                'custodio' => 0,
            ]);

            DB::commit();

            $radicado = VentanillaRadicaInterno::find($data['radica_interno_id']);
            if ($radicado) {
                $radicado->actualizarEstadoTrabajo();
            }

            return [
                'historial' => $historial->load(['usuarioOrigen', 'usuarioDestino', 'usersCargosDestino.cargo']),
                'responsable' => $responsable->load(['userCargo', 'radicaInterno']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
