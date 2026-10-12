<?php

namespace App\Http\Middleware;

use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosFirmas;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosProyectores;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosResponsable;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoDestinatarios;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoFirmantes;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoProyectores;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoResponsable;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciResponsable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Responde 404 si la asignación del parámetro de ruta (responsable, firmante, proyector o
 * destinatario) pertenece a un radicado que el usuario no ve (M01-08, M03-15..18). Si el cuerpo
 * mueve la asignación a otro radicado, ese destino también debe ser visible.
 *
 * Uso: ->middleware('acceso_asignacion:responsable_recibido')
 */
class AccesoAsignacion
{
    /** clave => [modelo de la asignación, columna del radicado, tipo para AccesoRadicado] */
    private const ASIGNACIONES = [
        'responsable_recibido' => [VentanillaRadicaReciResponsable::class, 'radica_reci_id', 'recibido'],
        'responsable_interno' => [VentanillaRadicaInternoResponsable::class, 'radica_interno_id', 'interno'],
        'proyector_interno' => [VentanillaRadicaInternoProyectores::class, 'radica_interno_id', 'interno'],
        'firmante_interno' => [VentanillaRadicaInternoFirmantes::class, 'radica_interno_id', 'interno'],
        'destinatario_interno' => [VentanillaRadicaInternoDestinatarios::class, 'radica_interno_id', 'interno'],
        'responsable_enviado' => [VentanillaRadicaEnviadosResponsable::class, 'radica_enviado_id', 'enviado'],
        'firmante_enviado' => [VentanillaRadicaEnviadosFirmas::class, 'radica_enviado_id', 'enviado'],
        'proyector_enviado' => [VentanillaRadicaEnviadosProyectores::class, 'radica_enviado_id', 'enviado'],
    ];

    public function handle(Request $request, Closure $next, string $clave): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        [$modelo, $columna, $tipo] = self::ASIGNACIONES[$clave];

        $id = (string) collect($request->route()->parameters())->first();
        $radicados = [ctype_digit($id) ? $modelo::whereKey($id)->value($columna) : null];

        $datos = $request->all();
        array_walk_recursive($datos, function ($valor, $campo) use ($columna, &$radicados) {
            if ($campo === $columna && $valor !== null) {
                $radicados[] = $valor;
            }
        });

        foreach ($radicados as $radicadoId) {
            if (! AccesoRadicado::permitido($user, $tipo, $radicadoId)) {
                abort(404, 'Asignación no encontrada');
            }
        }

        return $next($request);
    }
}
