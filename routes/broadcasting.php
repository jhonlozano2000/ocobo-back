<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Broadcasting Auth - WebSocket Authorization
|--------------------------------------------------------------------------
*/

Route::post('/broadcasting/auth', function (Request $request) {
    if (!auth()->check()) {
        return response()->json(['message' => 'No autenticado'], 401);
    }

    $appKey = config('reverb.app_key', 'ocobo-app-key-2024');
    $appSecret = config('reverb.app_secret', 'secret-key-2024');
    $user = auth()->user();
    $userId = $user->id;
    $userName = $user->name ?? $user->nombres ?? 'Usuario';

    return response()->json([
        'auth' => $appKey.':user-'.$userId,
        'channel_data' => json_encode([
            'user_id' => $userId,
            'user_info' => ['name' => $userName],
        ]),
        'shared_secret' => $appSecret,
    ]);
});
