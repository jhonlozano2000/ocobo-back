<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\ControlAcceso\UpdateNotificationSettingRequest;
use App\Models\ControlAcceso\UserNotificationSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationSettingsController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene la configuración de notificaciones del usuario autenticado.
     *
     * Este método retorna la configuración de notificaciones del usuario autenticado.
     * Si no existe una configuración, se crea una con valores por defecto.
     * Es útil para que los usuarios puedan revisar y modificar sus preferencias
     * de notificaciones.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la configuración
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Configuración de notificaciones obtenida exitosamente",
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "new_for_you": true,
     *     "account_activity": true,
     *     "new_browser_login": true,
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z"
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la configuración",
     *   "error": "Error message"
     * }
     */
    public function show()
    {
        try {
            $user = Auth::user();

            // Obtener o crear la configuración del usuario
            $settings = $user->notificationSettings()->firstOrCreate(
                [],
                [
                    'new_for_you' => true,
                    'account_activity' => true,
                    'new_browser_login' => true,
                ]
            );

            return $this->successResponse($settings, 'Configuración de notificaciones obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la configuración', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza la configuración de notificaciones del usuario autenticado.
     *
     * Este método permite al usuario modificar sus preferencias de notificaciones,
     * habilitando o deshabilitando diferentes tipos de notificaciones según
     * sus preferencias.
     *
     * @param UpdateNotificationSettingRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la configuración actualizada
     *
     * @bodyParam new_for_you boolean required Habilitar notificaciones nuevas. Example: true
     * @bodyParam account_activity boolean required Habilitar notificaciones de actividad de cuenta. Example: true
     * @bodyParam new_browser_login boolean required Habilitar notificaciones de nuevos inicios de sesión. Example: false
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Configuración actualizada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "new_for_you": true,
     *     "account_activity": true,
     *     "new_browser_login": false,
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "new_for_you": ["La configuración de notificaciones nuevas es obligatoria."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la configuración",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateNotificationSettingRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $validatedData = $request->validated();

            // Obtener o crear la configuración del usuario
            $settings = $user->notificationSettings()->firstOrCreate(
                [],
                [
                    'new_for_you' => true,
                    'account_activity' => true,
                    'new_browser_login' => true,
                ]
            );

            // Actualizar la configuración
            $settings->update($validatedData);

            DB::commit();

            return $this->successResponse($settings->fresh(), 'Configuración actualizada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la configuración', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene la configuración de notificaciones de un usuario específico (para administradores).
     *
     * Este método permite a los administradores obtener la configuración de
     * notificaciones de cualquier usuario del sistema.
     *
     * @param int $userId El ID del usuario
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la configuración
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Configuración de notificaciones obtenida exitosamente",
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "new_for_you": true,
     *     "account_activity": true,
     *     "new_browser_login": true
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la configuración",
     *   "error": "Error message"
     * }
     */
    public function getUserSettings(int $userId)
    {
        try {
            $user = \App\Models\User::find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            $settings = $user->notificationSettings()->firstOrCreate(
                [],
                [
                    'new_for_you' => true,
                    'account_activity' => true,
                    'new_browser_login' => true,
                ]
            );

            return $this->successResponse($settings, 'Configuración de notificaciones obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la configuración', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza la configuración de notificaciones de un usuario específico (para administradores).
     *
     * Este método permite a los administradores modificar la configuración de
     * notificaciones de cualquier usuario del sistema.
     *
     * @param UpdateNotificationSettingRequest $request La solicitud HTTP validada
     * @param int $userId El ID del usuario
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la configuración actualizada
     *
     * @urlParam userId integer required El ID del usuario. Example: 1
     * @bodyParam new_for_you boolean required Habilitar notificaciones nuevas. Example: true
     * @bodyParam account_activity boolean required Habilitar notificaciones de actividad de cuenta. Example: true
     * @bodyParam new_browser_login boolean required Habilitar notificaciones de nuevos inicios de sesión. Example: false
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Configuración actualizada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "new_for_you": true,
     *     "account_activity": true,
     *     "new_browser_login": false
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la configuración",
     *   "error": "Error message"
     * }
     */
    public function updateUserSettings(UpdateNotificationSettingRequest $request, int $userId)
    {
        try {
            DB::beginTransaction();

            $user = \App\Models\User::find($userId);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            $validatedData = $request->validated();

            // Obtener o crear la configuración del usuario
            $settings = $user->notificationSettings()->firstOrCreate(
                [],
                [
                    'new_for_you' => true,
                    'account_activity' => true,
                    'new_browser_login' => true,
                ]
            );

            // Actualizar la configuración
            $settings->update($validatedData);

            DB::commit();

            return $this->successResponse($settings->fresh(), 'Configuración actualizada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la configuración', $e->getMessage(), 500);
        }
    }
}
