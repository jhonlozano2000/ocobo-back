<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
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
        $limite = (int) env('THROTTLE_ZONE_LIMIT', 60);
        $periodo = env('THROTTLE_ZONE_PER', '1minute');

        \Illuminate\Support\Facades\RateLimiter::for('api', function ($request) use ($limite, $periodo) {
            return \Illuminate\Support\Facades\Limit::perMinute($limite)->by($request->user()?->id ?: $request->ip());
        });
    }
}