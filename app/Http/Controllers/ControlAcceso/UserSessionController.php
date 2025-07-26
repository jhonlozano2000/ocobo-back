<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\ControlAcceso\ListUserSessionRequest;
use App\Models\ControlAcceso\UsersSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Jenssegers\Agent\Agent;

class UserSessionController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene las sesiones recientes del usuario autenticado.
     *
     * Este método retorna las sesiones más recientes del usuario autenticado,
     * incluyendo información detallada sobre el navegador, dispositivo y
     * ubicación de cada sesión. Es útil para que los usuarios puedan
     * revisar su actividad de inicio de sesión.
     *
     * @param ListUserSessionRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las sesiones
     *
     * @queryParam limit integer Número de sesiones a obtener (por defecto: 6, máximo: 50). Example: 10
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Sesiones obtenidas exitosamente",
     *   "data": [
     *     {
     *       "browserName": "Chrome on Windows",
     *       "device": "Desktop",
     *       "location": "192.168.1.100",
     *       "date": "15 Jan 2024, 14:30",
     *       "browserIcon": "tabler-brand-windows"
     *     },
     *     {
     *       "browserName": "Safari on iOS",
     *       "device": "iPhone",
     *       "location": "192.168.1.101",
     *       "date": "14 Jan 2024, 09:15",
     *       "browserIcon": "tabler-device-mobile"
     *     }
     *   ]
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "limit": ["El límite no puede exceder 50."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las sesiones",
     *   "error": "Error message"
     * }
     */
    public function index(ListUserSessionRequest $request)
    {
        try {
            $user = Auth::user();
            $limit = $request->validated('limit') ?? 6;

            // Obtener las sesiones del usuario
            $sessions = $user->sessions()
                ->orderBy('last_login_at', 'desc')
                ->take($limit)
                ->get();

            $agent = new Agent();

            // Transformar los datos al formato que el frontend espera
            $formattedSessions = $sessions->map(function ($session) use ($agent) {
                $agent->setUserAgent($session->user_agent);

                return [
                    'id' => $session->id,
                    'browserName' => $agent->browser() . ' on ' . $agent->platform(),
                    'device' => $agent->device(),
                    'location' => $session->ip_address, // Para ubicación real se necesitaría un servicio de GeoIP
                    'date' => $session->last_login_at->format('d M Y, H:i'),
                    'browserIcon' => $this->getDeviceIcon($agent),
                    'user_agent' => $session->user_agent,
                    'ip_address' => $session->ip_address,
                    'last_login_at' => $session->last_login_at->toISOString(),
                ];
            });

            return $this->successResponse($formattedSessions, 'Sesiones obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las sesiones', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene todas las sesiones de un usuario específico (para administradores).
     *
     * Este método permite a los administradores obtener todas las sesiones
     * de un usuario específico, incluyendo información detallada sobre
     * cada sesión.
     *
     * @param ListUserSessionRequest $request La solicitud HTTP validada
     * @param int $userId El ID del usuario
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las sesiones
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     * @queryParam limit integer Número de sesiones a obtener (por defecto: 15, máximo: 50). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Sesiones del usuario obtenidas exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "browserName": "Chrome on Windows",
     *       "device": "Desktop",
     *       "location": "192.168.1.100",
     *       "date": "15 Jan 2024, 14:30",
     *       "browserIcon": "tabler-brand-windows"
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las sesiones",
     *   "error": "Error message"
     * }
     */
    public function getUserSessions(ListUserSessionRequest $request, int $userId)
    {
        try {
            $user = \App\Models\User::find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            $limit = $request->validated('limit') ?? 15;

            // Obtener las sesiones del usuario
            $sessions = $user->sessions()
                ->orderBy('last_login_at', 'desc')
                ->take($limit)
                ->get();

            $agent = new Agent();

            // Transformar los datos
            $formattedSessions = $sessions->map(function ($session) use ($agent) {
                $agent->setUserAgent($session->user_agent);

                return [
                    'id' => $session->id,
                    'browserName' => $agent->browser() . ' on ' . $agent->platform(),
                    'device' => $agent->device(),
                    'location' => $session->ip_address,
                    'date' => $session->last_login_at->format('d M Y, H:i'),
                    'browserIcon' => $this->getDeviceIcon($agent),
                    'user_agent' => $session->user_agent,
                    'ip_address' => $session->ip_address,
                    'last_login_at' => $session->last_login_at->toISOString(),
                ];
            });

            return $this->successResponse($formattedSessions, 'Sesiones del usuario obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las sesiones', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una sesión específica del usuario autenticado.
     *
     * Este método permite al usuario eliminar una sesión específica,
     * útil para cerrar sesiones en dispositivos no autorizados.
     *
     * @param int $sessionId El ID de la sesión a eliminar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam sessionId integer required El ID de la sesión a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Sesión eliminada exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Sesión no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar la sesión",
     *   "error": "Error message"
     * }
     */
    public function destroy(int $sessionId)
    {
        try {
            $user = Auth::user();
            $session = $user->sessions()->find($sessionId);

            if (!$session) {
                return $this->errorResponse('Sesión no encontrada', null, 404);
            }

            $session->delete();

            return $this->successResponse(null, 'Sesión eliminada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar la sesión', $e->getMessage(), 500);
        }
    }

    /**
     * Función de ayuda para obtener el ícono del dispositivo.
     *
     * @param Agent $agent Instancia del agente de usuario
     * @return string Nombre del ícono
     */
    private function getDeviceIcon(Agent $agent): string
    {
        if ($agent->isDesktop()) {
            if (str_contains(strtolower($agent->platform()), 'windows')) {
                return 'tabler-brand-windows';
            }
            if (str_contains(strtolower($agent->platform()), 'mac')) {
                return 'tabler-brand-apple';
            }
            return 'tabler-device-desktop';
        }

        if ($agent->isMobile()) {
            if (str_contains(strtolower($agent->platform()), 'android')) {
                return 'tabler-brand-android';
            }
            if (str_contains(strtolower($agent->platform()), 'ios')) {
                return 'tabler-device-mobile';
            }
            return 'tabler-device-mobile';
        }

        return 'tabler-device-desktop';
    }
}
