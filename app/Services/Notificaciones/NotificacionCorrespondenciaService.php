<?php

namespace App\Services\Notificaciones;

use App\Mail\RadicadoEnviadoNotification;
use App\Mail\RadicadoNotification;
use App\Models\VentanillaUnica\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class NotificacionCorrespondenciaService
{
    private const TIPO_NOTIFICACION = 'asignacion';

    /**
     * Envía notificación de correspondencia recibida a responsables.
     *
     * @param VentanillaRadicaReci $radicado
     * @return array
     */
    public function enviarRadicadoRecibido(VentanillaRadicaReci $radicado): array
    {
        $emails = $this->obtenerCorreosResponsables($radicado);
        $tipo = self::TIPO_NOTIFICACION;

        foreach ($emails as $email) {
            Mail::to($email)->send(new RadicadoNotification($radicado, $tipo));
        }

        return [
            'emails_enviados' => $emails->values()->all(),
            'total_enviados' => $emails->count(),
            'tipo_notificacion' => $tipo,
        ];
    }

    /**
     * Envía notificación de correspondencia enviada a responsables.
     *
     * @param VentanillaRadicaEnviados $radicado
     * @return array
     */
    public function enviarRadicadoEnviado(VentanillaRadicaEnviados $radicado): array
    {
        $emails = $this->obtenerCorreosResponsablesEnviado($radicado);
        $tipo = self::TIPO_NOTIFICACION;

        foreach ($emails as $email) {
            Mail::to($email)->send(new RadicadoEnviadoNotification($radicado, $tipo));
        }

        return [
            'emails_enviados' => $emails->values()->all(),
            'total_enviados' => $emails->count(),
            'tipo_notificacion' => $tipo,
        ];
    }

    /**
     * Obtiene correos únicos de responsables del radicado recibido.
     *
     * @param VentanillaRadicaReci $radicado
     * @return Collection
     */
    private function obtenerCorreosResponsables(VentanillaRadicaReci $radicado): Collection
    {
        return $radicado->responsables
            ->map(fn ($responsable) => $responsable->userCargo?->user?->email)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Obtiene correos únicos de responsables del radicado enviado.
     *
     * @param VentanillaRadicaEnviados $radicado
     * @return Collection
     */
    private function obtenerCorreosResponsablesEnviado(VentanillaRadicaEnviados $radicado): Collection
    {
        return $radicado->responsables
            ->map(fn ($responsable) => $responsable->userCargo?->user?->email)
            ->filter()
            ->unique()
            ->values();
    }
}
