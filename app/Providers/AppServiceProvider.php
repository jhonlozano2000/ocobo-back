<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use App\Contracts\Services\ConfigListaServiceInterface;
use App\Contracts\Services\ConfigDiviPoliServiceInterface;
use App\Contracts\Services\CalidadOrganigramaServiceInterface;
use App\Contracts\Services\UserServiceInterface;
use App\Contracts\Services\RoleServiceInterface;
use App\Contracts\Services\TRDServiceInterface;
use App\Services\Configuracion\ConfigListaService;
use App\Services\Configuracion\ConfigDiviPoliService;
use App\Services\Calidad\CalidadOrganigramaService;
use App\Services\ControlAcceso\UserService;
use App\Services\ControlAcceso\RoleService;
use App\Services\ClasificacionDocumental\TRDService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service Bindings (Interface → Implementation)
        $this->app->bind(ConfigListaServiceInterface::class, ConfigListaService::class);
        $this->app->bind(ConfigDiviPoliServiceInterface::class, ConfigDiviPoliService::class);
        $this->app->bind(CalidadOrganigramaServiceInterface::class, CalidadOrganigramaService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(RoleServiceInterface::class, RoleService::class);
        $this->app->bind(TRDServiceInterface::class, TRDService::class);
    }

    public function boot(Router $router): void
    {
        $this->configurarRateLimiting();
    }

    private function configurarRateLimiting(): void
    {
        // =========================================================================
        // RATE LIMITING - OWASP A07:2021, ISO 27001 A.12.4.2
        // =========================================================================

        // Rate limit general API: 60 req/min por IP o usuario
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Demasiadas solicitudes. Por favor espere.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });

        // Rate limit autenticación: 5 intentos/min por IP (Brute Force Protection)
        RateLimiter::for('login', function (Request $request) {
            $loginId = $request->input('email') ?: $request->ip();
            return Limit::perMinute(5)
                ->by($loginId)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Demasiados intentos de inicio de sesión. Intente en 1 minuto.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });

        // Rate limit registro: 3 registros/min por IP
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Demasiados intentos de registro. Intente más tarde.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });

        // Rate limit radicación: 30 req/min por usuario (ISO 27001 A.12.4)
        RateLimiter::for('radicacion', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Límite de radicaciones alcanzado. Intente en unos minutos.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });

        // Rate limit uploads: 10 uploads/min por usuario (OWASP A08)
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Límite de subida de archivos alcanzado.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });

        // Rate limit búsqueda: 30 req/min por usuario
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Rate limit firma electrónica: 5 req/min por usuario
        RateLimiter::for('firma', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Límite de solicitudes de firma alcanzado.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });

        // Rate limit específico para API de terceros (protección enumeration)
        RateLimiter::for('terceros', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Límite de consultas a terceros alcanzado.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });

        // Rate limit operaciones de configuración: 30 req/min por usuario
        RateLimiter::for('config-operations', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Límite de operaciones de configuración alcanzado.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });
    }
}