<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Broadcasting Auth - WebSocket Authorization
|--------------------------------------------------------------------------
*/

// Firma la suscripción (HMAC con el secreto de Reverb, que nunca sale del servidor) solo si el
// callback del canal en routes/channels.php autoriza al usuario; si no, 403.
Route::post('/broadcasting/auth', function (Request $request) {
    $user = auth('sanctum')->user();

    if (! $user) {
        return response()->json(['message' => 'No autenticado'], 401);
    }

    $request->setUserResolver(fn () => $user);

    return Broadcast::auth($request);
});
