<?php

namespace App\Listeners;

use App\Models\UsersAuthenticationLog;
use App\Models\ControlAcceso\UsersSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StoreUserSession
{
    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(Login $event): void
    {
        $userAgent = $this->request->userAgent();
        $parsed = $this->parseUserAgent($userAgent);

        $session = UsersSession::create([
            'user_id'         => $event->user->id,
            'ip_address'      => $this->request->ip(),
            'ip_address_extra'=> $this->getRealIp(),
            'user_agent'      => $userAgent,
            'device_type'     => $parsed['device_type'],
            'browser'         => $parsed['browser'],
            'operating_system'=> $parsed['operating_system'],
            'referer_url'     => $this->request->header('referer'),
            'last_login_at'   => now(),
            'is_active'       => true,
            'metadata'        => json_encode([
                'full_user_agent' => $userAgent,
                'http_accept'     => $this->request->header('accept'),
                'languages'       => $this->request->header('accept-language'),
            ])
        ]);

        UsersAuthenticationLog::logEvent([
            'user_id'  => $event->user->id,
            'event'    => 'login_success',
            'success'  => true,
            'details'  => "Sesión iniciada desde {$parsed['device_type']} - {$parsed['browser']} ({$parsed['operating_system']})",
        ]);

        Log::info('Sesión creada para usuario: ' . $event->user->id . ' | IP: ' . $this->request->ip() . ' | Dispositivo: ' . $parsed['device_type'] . ' - ' . $parsed['browser']);
    }

    private function parseUserAgent(string $userAgent): array
    {
        $deviceType = 'Desktop';
        $browser = 'Unknown';
        $os = 'Unknown';

        // Detectar dispositivo
        if (preg_match('/(mobile|android|iphone|ipad|tablet)/i', $userAgent)) {
            if (preg_match('/ipad/i', $userAgent)) {
                $deviceType = 'Tablet';
            } else {
                $deviceType = 'Mobile';
            }
        }

        // Detectar navegador
        if (preg_match('/Chrome/i', $userAgent) && !preg_match('/Edg/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edg/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/MSIE|Trident/i', $userAgent)) {
            $browser = 'Internet Explorer';
        }

        // Detectar sistema operativo
        if (preg_match('/Windows/i', $userAgent)) {
            $os = 'Windows';
            if (preg_match('/Windows 11/i', $userAgent)) {
                $os = 'Windows 11';
            } elseif (preg_match('/Windows 10/i', $userAgent)) {
                $os = 'Windows 10';
            }
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iOS|iPhone|iPad/i', $userAgent)) {
            $os = 'iOS';
        }

        return [
            'device_type'     => $deviceType,
            'browser'         => $browser,
            'operating_system'=> $os
        ];
    }

    private function getRealIp(): ?string
    {
        // Obtener IP real considerando proxys
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'] as $header) {
            $ip = $this->request->header($header);
            if ($ip) {
                return explode(',', $ip)[0];
            }
        }
        return null;
    }
}
