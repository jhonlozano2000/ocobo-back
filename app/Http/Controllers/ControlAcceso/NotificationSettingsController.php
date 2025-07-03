<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationSettingsController extends Controller
{
    public function show()
    {
        // Devuelve la configuración del usuario, o la crea si no existe.
        $settings = Auth::user()->notificationSettings()->firstOrCreate([]);

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'new_for_you'       => 'required|boolean',
            'account_activity'  => 'required|boolean',
            'new_browser_login' => 'required|boolean',
        ]);

        $settings = Auth::user()->notificationSettings()->update($validated);

        return response()->json($settings);
    }
}
