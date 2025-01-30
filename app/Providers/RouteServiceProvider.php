<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Aquí agregamos el nuevo archivo de rutas
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

            Route::middleware('api')
                ->prefix('api/ventanilla')
                ->group(base_path('routes/ventanilla.php'));
        });
    }
}
