<?php

namespace App\Http\Traits;

use App\Models\ControlAcceso\UserNotificationSetting;
use App\Models\Notificacion;

trait NotificationPreferenceTrait
{
    /**
     * Check if the user allows a specific notification type.
     * Returns true if no settings exist (default: all enabled).
     */
    private function userAllowsNotification(int $userId, string $type): bool
    {
        $preferenceKey = match ($type) {
            'tarea.asignada', 'radicado_asignado', 'asignacion' => 'new_for_you',
            default => 'account_activity',
        };

        $settings = UserNotificationSetting::forUser($userId);

        if (! $settings) {
            return true;
        }

        return $settings->isEnabled($preferenceKey);
    }

    /**
     * Create a notification only if the user allows it.
     * Returns the notification or null if suppressed.
     */
    private function createNotificationIfAllowed(array $data): ?Notificacion
    {
        if (! $this->userAllowsNotification($data['user_id'], $data['type'] ?? '')) {
            return null;
        }

        return Notificacion::create($data);
    }
}
