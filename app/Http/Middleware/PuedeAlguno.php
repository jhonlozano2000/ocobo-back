<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permite el acceso si el usuario tiene al menos uno de los permisos indicados.
 *
 * Uso: ->middleware('can_any:Gestión - Terceros -> Listar,Radicar -> PQRSF -> Crear')
 */
class PuedeAlguno
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        foreach ($permisos as $permiso) {
            if ($user->can($permiso)) {
                return $next($request);
            }
        }

        abort(403, 'No tiene permisos para realizar esta acción');
    }
}
