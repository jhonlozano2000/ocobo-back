<?php

namespace App\Services\Auth;

use App\Helpers\MailConfigHelper;
use App\Mail\NuevoInicioSesionMail;
use App\Models\ControlAcceso\UserNotificationSetting;
use App\Models\ControlAcceso\UsersSession;
use App\Models\Notificacion;
use App\Models\User;
use App\Models\UsersAuthenticationLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sesión concurrente desde otra IP (ISO 27001 A.9.4.2): se registra y se avisa al titular
 * según sus preferencias `new_browser_login` (app, activa por defecto) y `new_browser_login_email`.
 * No bloquea el login: el usuario puede cerrar las demás sesiones desde su perfil.
 */
class SesionConcurrenteService
{
    public function detectar(User $user, string $ip, ?string $userAgent): void
    {
        $sesionPrevia = UsersSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('ip_address', '!=', $ip)
            ->first();

        if (! $sesionPrevia) {
            return;
        }

        UsersAuthenticationLog::logEvent([
            'user_id' => $user->id,
            'event' => 'concurrent_session_detected',
            'success' => true,
            'email' => $user->email,
            'details' => "Nueva sesión iniciada desde IP {$ip} mientras existía sesión activa previa desde IP {$sesionPrevia->ip_address}",
        ]);

        // Un fallo al avisar no debe impedir el login
        try {
            $this->avisar($user, $ip, $userAgent);
        } catch (\Throwable $e) {
            Log::warning('No se pudo avisar la sesión concurrente', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    private function avisar(User $user, string $ip, ?string $userAgent): void
    {
        $preferencias = UserNotificationSetting::forUser($user->id);
        $fecha = now();

        if (! $preferencias || $preferencias->new_browser_login) {
            Notificacion::create([
                'user_id' => $user->id,
                'type' => 'seguridad.sesion_concurrente',
                'title' => 'Nuevo inicio de sesión',
                'message' => "Se inició sesión en tu cuenta desde la IP {$ip} mientras tenías otra sesión activa. Si no fuiste tú, cierra las demás sesiones y cambia tu contraseña.",
                'data' => ['url' => '/control-acceso/usuarios/mi-perfil', 'ip' => $ip],
            ]);
        }

        if ($preferencias?->new_browser_login_email && $user->email) {
            MailConfigHelper::configureFromConfigVarias();
            Mail::to($user->email)->send(new NuevoInicioSesionMail($user->nombres ?? '', $ip, substr((string) $userAgent, 0, 200), $fecha));
        }
    }
}
