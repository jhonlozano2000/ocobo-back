<?php

namespace App\Listeners;

use App\Models\UsersAuthenticationLog;
use App\Models\ControlAcceso\UsersSession;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class StoreUserLogout
{
    public function handle(Logout $event): void
    {
        $userId = $event->user?->id;
        $guard = $event->guards[0] ?? 'web';

        if ($userId && $guard === 'web') {
            $session = UsersSession::where('user_id', $userId)
                ->where('is_active', true)
                ->orderBy('last_login_at', 'desc')
                ->first();

            if ($session) {
                $duration = $session->last_login_at->diff(now())->format('%H:%i:%s');
                
                $session->update([
                    'logout_at' => now(),
                    'is_active' => false
                ]);

                UsersAuthenticationLog::logEvent([
                    'user_id'  => $userId,
                    'event'    => 'logout',
                    'success'  => true,
                    'details'  => "Sesión cerrada. Duración: {$duration}",
                ]);

                Log::info('Sesión cerrada para usuario: ' . $userId . ' | Duración: ' . $duration);
            }
        }
    }
}