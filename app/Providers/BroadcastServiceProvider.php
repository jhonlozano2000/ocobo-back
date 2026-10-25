<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Registra los canales privados. La ruta /broadcasting/auth está en routes/broadcasting.php
     * (grupo api, sesión de Sanctum), por eso no se llama a Broadcast::routes().
     */
    public function boot(): void
    {
        require base_path('routes/channels.php');
    }
}
