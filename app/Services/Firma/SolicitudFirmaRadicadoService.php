<?php

namespace App\Services\Firma;

use App\Models\Notificacion;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Support\Facades\DB;

class SolicitudFirmaRadicadoService
{
    public const TIPO_OTP = 'solicitud_firma_radicado_recibido';

    public function puedeSolicitar(VentanillaRadicaReci $radicado, User $usuario): bool
    {
        return (int) $radicado->usuario_crea === (int) $usuario->id
            || $usuario->can('Radicar -> Cores. Recibida -> Firmar');
    }

    /** Solicita la firma a los responsables distintos del radicador, sin firmar el documento. */
    public function solicitar(VentanillaRadicaReci $radicado, User $radicador): int
    {
        $responsables = $radicado->responsables()
            ->with('userCargo.user')
            ->get()
            ->map(fn ($responsable) => $responsable->userCargo?->user)
            ->filter(fn ($user) => $user && $user->id !== $radicador->id)
            ->unique('id');

        return DB::transaction(function () use ($radicado, $radicador, $responsables): int {
            VentanillaRadicaReci::query()->whereKey($radicado->id)->lockForUpdate()->firstOrFail();

            foreach ($responsables as $responsable) {
                Notificacion::firstOrCreate(
                    [
                        'user_id' => $responsable->id,
                        'type' => 'firma_solicitada',
                        'notifiable_type' => VentanillaRadicaReci::class,
                        'notifiable_id' => $radicado->id,
                        'read_at' => null,
                    ],
                    [
                        'title' => 'Firma pendiente de radicado recibido',
                        'message' => "Se solicita su firma electrónica para el radicado {$radicado->num_radicado}.",
                        'data' => [
                            'radica_reci_id' => $radicado->id,
                            'num_radicado' => $radicado->num_radicado,
                            'solicitado_por' => $radicador->id,
                            'url' => "/mi-bandeja/recibidos?detail={$radicado->id}&firmar=1",
                        ],
                    ]
                );
            }

            return $responsables->count();
        });
    }
}
