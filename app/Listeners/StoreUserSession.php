<?php

namespace App\Listeners;

use App\Models\ControlAcceso\UsersSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StoreUserSession
{
    public $request;
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(Login $event): void
    {

        Log::info('Evento de Login capturado para el usuario: ' . $event->user->id);

        UsersSession::create([
            'user_id'       => $event->user->id,
            'ip_address'    => $this->request->ip(),
            'user_agent'    => $this->request->userAgent(),
            'last_login_at' => now(),
        ]);
    }
}
