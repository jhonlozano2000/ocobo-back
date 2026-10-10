<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permite el acceso si el recurso pertenece al usuario autenticado
 * (parámetro de ruta {userId}) o si tiene el permiso indicado.
 *
 * Uso: ->middleware('propio_o_can:Control de acceso - Usuarios -> Mostrar')
 */
class PropioOPermiso
{
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $esPropio = (string) $request->route('userId') === (string) $user->id;

        if (! $esPropio && ! $user->can($permiso)) {
            abort(403, 'No tiene permisos para realizar esta acción');
        }

        return $next($request);
    }
}
