<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Responde 404 si el radicado del parámetro de ruta no es visible para el usuario según
 * el filtrado ABAC jerárquico del módulo (M01-01/05, M12-01, M04-01, M02-01/02/03, M03-03).
 *
 * Uso: ->middleware('acceso_radicado:recibido,id')
 */
class AccesoRadicado
{
    public function handle(Request $request, Closure $next, string $tipo, string $parametro = 'id'): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! self::permitido($user, $tipo, $request->route($parametro))) {
            abort(404, 'Radicado no encontrado');
        }

        return $next($request);
    }

    /**
     * Si el radicado $id del tipo dado es visible para el usuario (ABAC del módulo).
     */
    public static function permitido(User $user, string $tipo, mixed $id): bool
    {
        $id = (string) $id;

        return ctype_digit($id) && match ($tipo) {
            'recibido' => VentanillaRadicaReci::accesiblePara($user, (int) $id),
            'pqrs' => VentanillaPqrs::accesiblePara($user, (int) $id),
            'enviado' => VentanillaRadicaEnviados::accesiblePara($user, (int) $id),
            'interno' => VentanillaRadicaInterno::accesiblePara($user, (int) $id),
        };
    }
}
