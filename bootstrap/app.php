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
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
            \App\Http\Middleware\AuditLogMiddleware::class,
            \App\Http\Middleware\ValidateContentType::class,
            \App\Http\Middleware\ValidateRequestSize::class,
            \App\Http\Middleware\DataIntegrity::class,
            \App\Http\Middleware\CombinedSecurityMiddleware::class,
            \App\Http\Middleware\SanitizeResponse::class,
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
            'password.strength' => \App\Http\Middleware\ValidatePasswordStrength::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);

        // No mostrar detalles de errores en producción (OWASP A05)
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = 500;

                if (method_exists($e, 'getStatusCode')) {
                    $statusCode = $e->getStatusCode();
                }

                $response = [
                    'success' => false,
                    'message' => 'Ha ocurrido un error. Contacte al administrador.',
                    'error' => 'SERVER_ERROR',
                ];

                // En desarrollo/local, incluir más información
                if (config('app.debug') && config('app.env') !== 'production') {
                    $response['debug'] = [
                        'message' => $e->getMessage(),
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ];
                }

                return response()->json($response, $statusCode);
            }
        });

        $exceptions->report(function (\Throwable $e) {
            // Registrar errores críticos con contexto adicional
            if (config('app.env') === 'production') {
                Log::error('Exception reportado', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'user_id' => auth()->id(),
                    'request_id' => request()->header('X-Request-ID'),
                ]);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.',
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para realizar esta acción.',
                ], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación.',
                    'errors' => $e->errors(),
                ], 422);
            }
});
    })
    ->create();
