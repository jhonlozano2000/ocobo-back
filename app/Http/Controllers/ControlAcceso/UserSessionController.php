<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Jenssegers\Agent\Agent;

class UserSessionController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Obtenemos las últimas 6 sesiones del usuario
        $sessions = $user->sessions()->take(6)->get();

        $agent = new Agent();

        // Transformamos los datos al formato que el frontend espera
        $formattedSessions = $sessions->map(function ($session) use ($agent) {
            $agent->setUserAgent($session->user_agent);

            return [
                'browserName' => $agent->browser() . ' on ' . $agent->platform(),
                'device'      => $agent->device(),
                'location'    => $session->ip_address, // Para una ubicación real se necesitaría un servicio de GeoIP
                'date'        => $session->last_login_at->format('d M Y, H:i'),
                'browserIcon' => $this->getDeviceIcon($agent),
            ];
        });

        return response()->json($formattedSessions);
    }

    // Función de ayuda para obtener el ícono
    private function getDeviceIcon(Agent $agent)
    {
        if ($agent->isDesktop()) {
            if (str_contains(strtolower($agent->platform()), 'windows')) return 'tabler-brand-windows';
            if (str_contains(strtolower($agent->platform()), 'mac')) return 'tabler-brand-apple';
            return 'tabler-device-desktop';
        }
        if ($agent->isMobile()) {
            if (str_contains(strtolower($agent->platform()), 'android')) return 'tabler-brand-android';
            if (str_contains(strtolower($agent->platform()), 'ios')) return 'tabler-device-mobile'; // Para iPhone/iPad
            return 'tabler-device-mobile';
        }
        return 'tabler-device-desktop';
    }
}
