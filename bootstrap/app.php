<?php

use App\Http\Middleware\AuditLogMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CombinedSecurityMiddleware;
use App\Http\Middleware\DataIntegrity;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnsureApiAuthenticated;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SanitizeResponse;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateContentType;
use App\Http\Middleware\ValidatePasswordStrength;
use App\Http\Middleware\ValidateRequestSize;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            TrustProxies::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
        ]);

        // Middleware groups
        $middleware->group('web', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ]);

        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class,
            StartSession::class,
            AddQueuedCookiesToResponse::class,
            SecurityHeadersMiddleware::class,
            AuditLogMiddleware::class,
            ValidateContentType::class,
            ValidateRequestSize::class,
            DataIntegrity::class,
            CombinedSecurityMiddleware::class,
            SanitizeResponse::class,
            ThrottleRequests::class.':api',
            HandleCors::class,
            SubstituteBindings::class,
        ]);

        // Aliases
        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'signed' => ValidateSignature::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
            'api.auth' => EnsureApiAuthenticated::class,
            'cache.headers' => SetCacheHeaders::class,
            'can' => Authorize::class,
            'password.strength' => ValidatePasswordStrength::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $exceptions->report(function (Throwable $e) {
            if (config('app.env') === 'production') {
                Log::error('Exception reportado', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'user_id' => auth()->id(),
                    'request_id' => request()->header('X-Request-ID'),
                ]);
            }
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.',
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para realizar esta acción.',
                ], 403);
            }
        });

        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = 500;

                if ($e instanceof HttpResponseException) {
                    $statusCode = $e->getResponse()->getStatusCode();
                } elseif (method_exists($e, 'getStatusCode')) {
                    $statusCode = $e->getStatusCode();
                }

                $response = [
                    'success' => false,
                    'message' => 'Ha ocurrido un error. Contacte al administrador.',
                    'error' => 'SERVER_ERROR',
                ];

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
    })
    ->create();
