<?php

namespace App\Http\Middleware;

use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los archivos de una PQRS viven en su radicado recibido y se gestionan por las rutas de Recibida.
 * Permite la acción con el permiso de Recibida o, si el radicado tiene PQRS, con el de PQRSF.
 *
 * Uso: ->middleware('permiso_archivo_recibido:Subir adjuntos')
 */
class PermisoArchivoRecibido
{
    public function handle(Request $request, Closure $next, string $accion, string $parametro = 'id'): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->can("Radicar -> Cores. Recibida -> {$accion}")) {
            return $next($request);
        }

        $id = (string) $request->route($parametro);

        if (ctype_digit($id)
            && $user->can("Radicar -> PQRSF -> {$accion}")
            && VentanillaPqrs::where('ventanilla_radica_reci_id', (int) $id)->exists()) {
            return $next($request);
        }

        abort(403, 'No tiene permisos para realizar esta acción');
    }
}
