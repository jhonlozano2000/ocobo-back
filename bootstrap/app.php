<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Validación de configuración de seguridad en cada request
        $middleware->use([
            \App\Http\Middleware\TrustProxies::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \App\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        // Middleware groups
        $middleware->group('web', [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->group('api', [
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
            \App\Http\Middleware\AuditLogMiddleware::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Aliases
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'signed' => \App\Http\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'api.auth' => \App\Http\Middleware\EnsureApiAuthenticated::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 401);
            }
        });

        $exceptions->report(function (Throwable $e) {
            //
        });
    })
    ->beforeBootstrapping(function (Application $app) {
        // =========================================================================
        // SECURITY VALIDATION - OWASP A05, ISO 27001 A.12.4
        // =========================================================================
        
        // 1. APP_DEBUG no puede ser true en producción
        if (config('app.env') === 'production' && config('app.debug') === true) {
            Log::critical('SECURITY: APP_DEBUG is TRUE in production environment');
            if (!$app->runningUnitTests()) {
                throw new \RuntimeException('Security Alert: APP_DEBUG cannot be true in production');
            }
        }
        
        // 2. DB_PASSWORD es obligatoria en producción
        if (config('app.env') === 'production' && empty(config('database.connections.mysql.password'))) {
            Log::critical('SECURITY: DB_PASSWORD is empty in production environment');
            if (!$app->runningUnitTests()) {
                throw new \RuntimeException('Security Alert: DB_PASSWORD is required for production');
            }
        }
        
        // 3. Session lifetime no puede exceder 30 minutos en producción
        if (config('app.env') === 'production' && config('session.lifetime') > 30) {
            Log::warning('SECURITY: SESSION_LIFETIME exceeds 30 minutes in production', [
                'current_lifetime' => config('session.lifetime')
            ]);
        }
        
        // 4. Rate limiting debe estar habilitado
        $rateLimitConfig = config('cache.default');
        if ($rateLimitConfig === 'file' && config('app.env') === 'production') {
            Log::warning('SECURITY: Using file cache driver for rate limiting in production');
        }
        
        Log::info('Security configuration validation completed', [
            'env' => config('app.env'),
            'debug' => config('app.debug'),
            'session_lifetime' => config('session.lifetime'),
            'rate_limit_driver' => config('cache.default'),
            'validated_at' => now()->toISOString()
        ]);
    })
    ->create();
