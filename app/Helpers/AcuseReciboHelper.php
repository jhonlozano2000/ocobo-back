<?php

namespace App\Helpers;

use App\Mail\AcuseReciboRadicado;
use App\Mail\RadicadoRecibidoNotificacionTercero;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Helper centralizado para envío de acuses de recibo.
 * Funciona para correspondencia recibida, enviada e interna.
 */
class AcuseReciboHelper
{
    /**
     * Envía el acuse de recibo automático al tercero/ciudadano.
     *
     * Uso:
     *   AcuseReciboHelper::enviar($radicado);              // recibida
     *   AcuseReciboHelper::enviar($radicado, 'enviada');   // enviada
     *   AcuseReciboHelper::enviar($radicado, 'interna');   // interna
     *
     * Reglas:
     *  - Solo envía si el tercero tiene email y notifica_email = true
     *  - El error en el envío NO interrumpe el flujo principal
     *  - Registra en log éxito y error
     *
     * @param  mixed  $radicado   Modelo de radicado (recibida, enviada o interna)
     * @param  string $tipo       Tipo de correspondencia: recibida | enviada | interna
     * @return bool               true si se envió, false si no aplica o hubo error
     */
    public static function enviar(mixed $radicado, string $tipo = 'recibida'): bool
    {
        try {
            // Configurar el mailer desde config_varias
            MailConfigHelper::configureFromConfigVarias();

            // Obtener el tercero según el tipo de correspondencia
            $tercero = static::obtenerTercero($radicado, $tipo);

            // Verificar que tiene email y acepta notificaciones
            if (!$tercero || empty($tercero->email) || !$tercero->notifica_email) {
                return false;
            }

            Mail::to($tercero->email)
                ->send(new AcuseReciboRadicado($radicado, $tipo));

            Log::info('Acuse de recibo enviado', [
                'tipo'         => $tipo,
                'radicado_id'  => $radicado->id,
                'num_radicado' => $radicado->num_radicado ?? $radicado->id,
                'email'        => $tercero->email,
            ]);

            return true;

        } catch (\Exception $e) {
            // El error NO falla la creación del radicado
            Log::error('Error al enviar acuse de recibo', [
                'tipo'        => $tipo,
                'radicado_id' => $radicado->id ?? null,
                'error'       => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Envía notificación al tercero con archivos adjuntos al radicar.
     * Solo envía si la configuración 'notificar_radicado_al_tercero' está habilitada (solo en radicación).
     *
     * @param  mixed  $radicado   Modelo de radicado recibida
     * @param  bool  $skipConfigCheck  Si es true, omite la verificación de configuración (para botón manual)
     * @return bool               true si se envió, false si no aplica o hubo error
     */
    public static function enviarNotificacionConAdjuntos(mixed $radicado, bool $skipConfigCheck = false): bool
    {
        try {
            // Verificar si la configuración está habilitada (solo si no es llamada manual)
            if (!$skipConfigCheck) {
                $configurado = ConfigVarias::getValor('notificar_radicado_al_tercero', 'false');
                if ($configurado !== 'true') {
                    Log::debug('Notificación al tercero deshabilitada por configuración');
                    return false;
                }
            }

            // Configurar el mailer desde config_varias
            MailConfigHelper::configureFromConfigVarias();

            // Obtener el tercero
            $tercero = $radicado->relationLoaded('tercero')
                ? $radicado->tercero
                : $radicado->load('tercero')->tercero;

            // Verificar que tiene email y acepta notificaciones
            if (!$tercero || empty($tercero->email) || !$tercero->notifica_email) {
                Log::debug('Tercero no tiene email o no acepta notificaciones');
                return false;
            }

            Mail::to($tercero->email)
                ->send(new RadicadoRecibidoNotificacionTercero($radicado));

            Log::info('Notificación con adjuntos enviada al tercero', [
                'radicado_id'  => $radicado->id,
                'num_radicado' => $radicado->num_radicado ?? $radicado->id,
                'email'        => $tercero->email,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error al enviar notificación con adjuntos', [
                'radicado_id' => $radicado->id ?? null,
                'error'       => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Obtiene el tercero del radicado según el tipo de correspondencia.
     * Carga la relación si no está cargada (evita N+1).
     */
    private static function obtenerTercero(mixed $radicado, string $tipo): mixed
    {
        return match($tipo) {
            // Recibida: el tercero es quien envía el documento a la entidad
            'recibida' => $radicado->relationLoaded('tercero')
                ? $radicado->tercero
                : $radicado->load('tercero')->tercero,

            // Enviada: el tercero es el destinatario externo
            'enviada' => $radicado->relationLoaded('tercero')
                ? $radicado->tercero
                : $radicado->load('tercero')->tercero,

            // Interna: no aplica acuse a tercero externo
            'interna' => null,

            default => null,
        };
    }
}
