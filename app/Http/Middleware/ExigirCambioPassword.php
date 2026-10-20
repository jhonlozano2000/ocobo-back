<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras el usuario tenga una contraseña temporal (debe_cambiar_password), solo puede
 * consultar su sesión, cambiar la contraseña o cerrar sesión (ISO 27001 A.9.2.4).
 */
class ExigirCambioPassword
{
    public const CODIGO = 'password_change_required';

    private const RUTAS_PERMITIDAS = [
        'api/getme',
        'api/logout',
        'api/auth/check',
        'api/auth/check-permissions',
        'api/control-acceso/user/changePassword',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if ($user?->debe_cambiar_password && ! $request->is(...self::RUTAS_PERMITIDAS)) {
            return response()->json([
                'success' => false,
                'message' => 'Debes cambiar tu contraseña antes de continuar.',
                'code' => self::CODIGO,
            ], 403);
        }

        return $next($request);
    }
}
