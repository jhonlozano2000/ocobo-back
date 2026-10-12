<?php

namespace App\Providers;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use App\Policies\MiBandeja\TempReci\DocumentoPolicy;
use App\Policies\UserPolicy;
use App\Policies\Workflows\TareaPolicy;
use App\Policies\Workflows\WorkflowPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    // VentanillaRadicaReci no tiene policy: lo autorizan los middleware de ruta can: y acceso_radicado (M01-03)
    protected $policies = [
        User::class => UserPolicy::class,
        Documento::class => DocumentoPolicy::class,
        Workflow::class => WorkflowPolicy::class,
        Tarea::class => TareaPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
