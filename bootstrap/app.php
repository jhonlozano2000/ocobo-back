<?php

use App\Http\Middleware\AccesoAsignacion;
use App\Http\Middleware\AccesoRadicado;
use App\Http\Middleware\AuditLogMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CombinedSecurityMiddleware;
use App\Http\Middleware\DataIntegrity;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnsureApiAuthenticated;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\PropioOPermiso;
use App\Http\Middleware\PuedeAlguno;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SanitizeResponse;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateContentType;
use App\Http\Middleware\ValidateRequestSize;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            SecurityHeadersMiddleware::class,
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
            'propio_o_can' => PropioOPermiso::class,
            'can_any' => PuedeAlguno::class,
            'acceso_radicado' => AccesoRadicado::class,
            'acceso_asignacion' => AccesoAsignacion::class,
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
                $firstError = collect($e->errors())->flatten()->first() ?? 'Error de validación.';

                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => $firstError,
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => 'No autenticado o su sesión ha expirado.',
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => 'No tiene permisos para realizar esta acción.',
                ], 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $mensaje = $e->getPrevious() instanceof ModelNotFoundException
                    ? 'El recurso solicitado no fue encontrado.'
                    : ($e->getMessage() ?: 'Recurso o endpoint no encontrado.');

                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => $mensaje,
                ], 404);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => 'Demasiadas solicitudes. Por favor, espere un momento antes de reintentar.',
                    'error' => 'TOO_MANY_REQUESTS',
                ], 429);
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
                    'status' => false,
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
