<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);

        // Techo Máximo Absoluto de Sesión (ISO 27001 A.9.4.2 / OWASP)
        // Previene sesiones zombies o perpetuas provocadas por polling en segundo plano
        if ($request->hasSession() && $request->session()->has('auth_login_at')) {
            $maxLifetime = (int) config('session.absolute_lifetime', 43200); // 12 horas default
            $elapsed = now()->timestamp - (int) $request->session()->get('auth_login_at');

            if ($elapsed > $maxLifetime) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->json([
                    'status' => false,
                    'message' => 'Sesión expirada por límite de tiempo máximo. Por favor, inicia sesión nuevamente.',
                ], 401);
            }
        }

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Para rutas API, siempre retornar null (no redirigir)
        if ($request->is('api/*')) {
            return null;
        }

        return $request->expectsJson() ? null : route('login');
    }
}
