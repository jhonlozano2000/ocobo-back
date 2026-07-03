<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('config-operations', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }

    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // BROADCASTING - WebSocket Authorization (para testing, acepta cualquier usuario)
            Route::post('/broadcasting/auth', function (Request $request) {
                $appKey = config('reverb.app_key', 'ocobo-app-key-2024');
                $appSecret = config('reverb.app_secret', 'secret-key-2024');

                // Para testing: usar usuario 1 si no hay sesión
                $userId = auth()->id() ?? 1;
                $user = auth()->user() ?? User::find(1);
                $userName = $user?->name ?? $user?->nombres ?? 'Test User';

                return response()->json([
                    'auth' => $appKey.':user-'.$userId,
                    'channel_data' => json_encode([
                        'user_id' => $userId,
                        'user_info' => ['name' => $userName],
                    ]),
                    'shared_secret' => $appSecret,
                ]);
            });

            Route::middleware('api')
                ->prefix('api/control-acceso')
                ->group(base_path('routes/controlAcceso.php'));

            Route::middleware('api')
                ->prefix('api/calidad')
                ->group(base_path('routes/calidad.php'));

            Route::middleware('api')
                ->prefix('api/config')
                ->group(base_path('routes/configuracion.php'));

            Route::middleware('api')
                ->prefix('api/clasifica-documental')
                ->group(base_path('routes/clasifica_documental.php'));

            Route::middleware('api')
                ->prefix('api/gestion')
                ->group(base_path('routes/gestion.php'));

            // MODULOS DE VENTANILLA ÚNICA
            Route::middleware('api')
                ->prefix('api/ventanilla')
                ->group(base_path('routes/ventanilla-recibida.php'));

            Route::middleware('api')
                ->prefix('api/ventanilla')
                ->group(base_path('routes/ventanilla-enviada.php'));

            Route::middleware('api')
                ->prefix('api/ventanilla')
                ->group(base_path('routes/ventanilla-interno.php'));

            Route::middleware('api')
                ->prefix('api/ventanilla')
                ->group(base_path('routes/ventanilla-pqrs.php'));

            // VENTANILLA UNICA - EMAIL RADICADOS
            Route::middleware('api')
                ->prefix('api/ventanilla')
                ->group(base_path('routes/ventanilla-email-radicados.php'));

            // SERVICIOS TRANSVERSALES (FIRMA, ETC)
            Route::middleware('api')
                ->prefix('api/transversal')
                ->group(base_path('routes/transversal.php'));

            // MODULOS MI BANDEJA
            Route::middleware('api')
                ->prefix('api/mi-bandeja')
                ->group(base_path('routes/mi-bandeja-temp-recibidos.php'));

            Route::middleware('api')
                ->prefix('api/mi-bandeja/recibidos')
                ->group(base_path('routes/mi-bandeja-recibidos.php'));

            Route::middleware('api')
                ->prefix('api/mi-bandeja/enviados')
                ->group(base_path('routes/mi-bandeja-enviados.php'));

            Route::middleware('api')
                ->prefix('api/mi-bandeja/internos')
                ->group(base_path('routes/mi-bandeja-internos.php'));

            Route::middleware('api')
                ->prefix('api/mi-bandeja')
                ->group(base_path('routes/mi-bandeja-temp.php'));

            // GESTIÓN DE ARCHIVO — PRÉSTAMOS
            Route::middleware('api')
                ->prefix('api/archivo')
                ->group(base_path('routes/archivo-prestamos.php'));

            // GESTIÓN DE ARCHIVO — TRANSFERENCIAS Y ELIMINACIONES
            Route::middleware('api')
                ->prefix('api/archivo')
                ->group(base_path('routes/archivo-transferencias.php'));

            // GESTIÓN DE ARCHIVO — REPORTES
            Route::middleware('api')
                ->prefix('api/archivo')
                ->group(base_path('routes/archivo-reportes.php'));

            // GESTIÓN DE ARCHIVO — DASHBOARD
            Route::middleware('api')
                ->prefix('api/archivo/dashboard')
                ->group(base_path('routes/archivo-dashboard.php'));

            // GESTIÓN DE ARCHIVO — PLANTILLAS DE DOCUMENTOS
            Route::middleware('api')
                ->prefix('api/archivo')
                ->group(base_path('routes/archivo-plantillas.php'));
        });
    }
}
