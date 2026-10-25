<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlAcceso\UpdateNotificationSettingRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ControlAcceso\UserNotificationSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Preferencias de notificación por categoría y canal (UserNotificationSetting::CATEGORIAS):
 * app = campana, email = correo, browser = aviso emergente en tiempo real.
 */
class NotificationSettingsController extends Controller
{
    use ApiResponseTrait;

    /**
     * Preferencias del usuario autenticado. Si no tiene, se crean con los valores por defecto.
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Configuración de notificaciones obtenida exitosamente",
     *   "data": {
     *     "new_for_you": {"app": true, "email": false, "browser": true},
     *     "vencimientos": {"app": true, "email": true, "browser": true},
     *     "account_activity": {"app": true, "browser": true},
     *     "new_browser_login": {"app": true, "email": false, "browser": false}
     *   }
     * }
     */
    public function show(): JsonResponse
    {
        return $this->respuesta(Auth::user(), 'Configuración de notificaciones obtenida exitosamente');
    }

    /**
     * Actualiza las preferencias del usuario autenticado. Solo se guardan las categorías y canales enviados.
     *
     * @bodyParam new_for_you object Canales de «Nuevas para ti». Example: {"app": true, "email": false, "browser": true}
     * @bodyParam vencimientos object Canales de «Vencimientos próximos». Example: {"app": true, "email": true, "browser": true}
     * @bodyParam account_activity object Canales de «Actividad de cuenta». Example: {"app": true, "browser": false}
     * @bodyParam new_browser_login object Canales de «Nuevo inicio de sesión». Example: {"app": true, "email": true, "browser": false}
     */
    public function update(UpdateNotificationSettingRequest $request): JsonResponse
    {
        return $this->guardar(Auth::user(), $request->validated());
    }

    /**
     * Preferencias de otro usuario (administración).
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     *
     * @response 404 {"status": false, "message": "Usuario no encontrado"}
     */
    public function getUserSettings(int $userId): JsonResponse
    {
        $user = User::find($userId);

        return $user
            ? $this->respuesta($user, 'Configuración de notificaciones obtenida exitosamente')
            : $this->notFoundResponse('Usuario no encontrado');
    }

    /**
     * Actualiza las preferencias de otro usuario (administración).
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     *
     * @response 404 {"status": false, "message": "Usuario no encontrado"}
     */
    public function updateUserSettings(UpdateNotificationSettingRequest $request, int $userId): JsonResponse
    {
        $user = User::find($userId);

        return $user ? $this->guardar($user, $request->validated()) : $this->notFoundResponse('Usuario no encontrado');
    }

    private function preferencias(User $user): UserNotificationSetting
    {
        // Sin atributos: la fila toma los valores por defecto de la tabla
        return $user->notificationSettings()->firstOrCreate([])->refresh();
    }

    private function respuesta(User $user, string $mensaje): JsonResponse
    {
        return $this->successResponse($this->preferencias($user)->formulario(), $mensaje);
    }

    private function guardar(User $user, array $formulario): JsonResponse
    {
        $preferencias = $this->preferencias($user);
        $preferencias->update(UserNotificationSetting::columnasDesde($formulario));

        return $this->successResponse($preferencias->formulario(), 'Configuración actualizada exitosamente');
    }
}
