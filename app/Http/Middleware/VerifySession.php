<?php

namespace App\Http\Middleware;

use App\Models\UsersAuthenticationLog;
use App\Models\ControlAcceso\UsersSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifySession
{
    protected const MAX_FAILED_ATTEMPTS = 5;
    protected const LOCKOUT_MINUTES = 15;

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldVerifySession($request)) {
            return $next($request);
        }

        // Solo verificar que el usuario esté autenticado via cookie de sesión
        // NO verificamos la tabla users_sessions (eso es para auditoría, no para autenticación)
        if (!auth()->check()) {
            $this->logEvent('session_invalid', null, false, 'No hay usuario autenticado - cookie inválida');
            return $this->handleUnauthorized($request);
        }

        $user = auth()->user();

        // Verificar si la cuenta está activa
        if ($user->estado == 0) {
            $this->logEvent('session_invalid', $user->id, false, 'Cuenta desactivada');
            auth()->logout();
            return $this->handleUnauthorized($request);
        }

        return $next($request);
    }

    protected function shouldVerifySession(Request $request): bool
    {
        // Rutas que NO requieren verificación de sesión
        $exceptRoutes = [
            'api.login',
            'api.register',
            'api.logout',
            'api.refresh',
            'sanctum.csrf-cookie',
            'api.test-session',
        ];

        $path = $request->path();
        
        // Rutas explícitas que no requieren verificación
        $publicPaths = ['api/login', 'api/register', 'api/logout', 'sanctum/csrf-cookie'];
        foreach ($publicPaths as $publicPath) {
            if (str_starts_with($path, $publicPath)) {
                return false;
            }
        }

        // Solo verificar rutas API que no son públicas
        return $request->is('api/*') && !str_starts_with($path, 'api/login') && !str_starts_with($path, 'api/register');
    }

    protected function isSessionValid($user): bool
    {
        $activeSession = UsersSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('last_login_at', '>=', now()->subHours(24))
            ->exists();

        return $activeSession;
    }

    protected function detectSessionFixation($user): bool
    {
        $sessionId = session()->getId();
        $lastSessionId = cache()->get("session_fixation_{$user->id}");

        if ($lastSessionId && $lastSessionId !== $sessionId) {
            return true;
        }

        cache()->put("session_fixation_{$user->id}", $sessionId, now()->addMinutes(30));

        return false;
    }

    protected function isIpBlocked(string $ip): bool
    {
        $failedAttempts = AuthenticationLog::where('ip_address', $ip)
            ->where('event', 'login_failed')
            ->where('created_at', '>=', now()->subMinutes(self::LOCKOUT_MINUTES))
            ->count();

        return $failedAttempts >= self::MAX_FAILED_ATTEMPTS * 2;
    }

    protected function isUserLocked($user): bool
    {
        $failedAttempts = AuthenticationLog::where('user_id', $user->id)
            ->where('event', 'login_failed')
            ->where('created_at', '>=', now()->subMinutes(self::LOCKOUT_MINUTES))
            ->count();

        return $failedAttempts >= self::MAX_FAILED_ATTEMPTS;
    }

    protected function getLockoutTime($user): string
    {
        $lastFailed = AuthenticationLog::where('user_id', $user->id)
            ->where('event', 'login_failed')
            ->orderByDesc('created_at')
            ->first();

        if ($lastFailed) {
            $unlockAt = $lastFailed->created_at->addMinutes(self::LOCKOUT_MINUTES);
            return $unlockAt->format('Y-m-d H:i:s');
        }

        return now()->addMinutes(self::LOCKOUT_MINUTES)->format('Y-m-d H:i:s');
    }

    protected function lockUser($user): void
    {
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
    }

    protected function handleUnauthorized(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Sesión no válida o expirada',
                'error' => 'unauthenticated'
            ], 401);
        }

        return redirect()->route('login')->with('error', 'Tu sesión ha expirado o no es válida');
    }

    protected function logEvent(string $event, ?int $userId, bool $success, ?string $details): void
    {
        UsersAuthenticationLog::logEvent([
            'user_id'  => $userId,
            'event'    => $event,
            'success'  => $success,
            'details'  => $details,
        ]);

        Log::channel('auth')->info("Auth Event: {$event}", [
            'user_id' => $userId,
            'success' => $success,
            'details' => $details
        ]);
    }
}