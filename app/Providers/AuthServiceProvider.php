<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Policies\VentanillaRadicaReciPolicy;
use App\Policies\UserPolicy;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\User;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Policies\MiBandeja\TempReci\DocumentoPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        VentanillaRadicaReci::class => VentanillaRadicaReciPolicy::class,
        User::class => UserPolicy::class,
        Documento::class => DocumentoPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}