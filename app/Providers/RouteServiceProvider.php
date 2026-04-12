<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = "/home";

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
            Route::middleware("api")
                ->prefix("api")
                ->group(base_path("routes/api.php"));

            Route::middleware("web")
                ->group(base_path("routes/web.php"));

            Route::middleware("api")
                ->prefix("api/control-acceso")
                ->group(base_path("routes/controlAcceso.php"));

            Route::middleware("api")
                ->prefix("api/calidad")
                ->group(base_path("routes/calidad.php"));

            Route::middleware("api")
                ->prefix("api/config")
                ->group(base_path("routes/configuracion.php"));

            Route::middleware("api")
                ->prefix("api/clasifica-documental")
                ->group(base_path("routes/clasifica_documental.php"));

            Route::middleware("api")
                ->prefix("api/gestion")
                ->group(base_path("routes/gestion.php"));

            // MODULOS DE VENTANILLA ÚNICA
            Route::middleware("api")
                ->prefix("api/ventanilla")
                ->group(base_path("routes/ventanilla-recibida.php"));

            Route::middleware("api")
                ->prefix("api/ventanilla")
                ->group(base_path("routes/ventanilla-enviada.php"));

            Route::middleware("api")
                ->prefix("api/ventanilla")
                ->group(base_path("routes/ventanilla-interno.php"));

            // SERVICIOS TRANSVERSALES (FIRMA, ETC)
            Route::middleware("api")
                ->prefix("api/transversal")
                ->group(base_path("routes/transversal.php"));
        });
    }
}
