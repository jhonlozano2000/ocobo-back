<?php

namespace App\Http\Controllers\MiBandeja\Concerns;

use App\Models\MiBandeja\MiBandejaTemp;
use Illuminate\Support\Facades\Auth;

/**
 * Autorización de acceso a grupos colaborativos.
 *
 * Previene IDOR horizontal: los endpoints operan por ID pero los permisos
 * son globales, por lo que DEBE verificarse que el usuario sea creador o
 * miembro (revisor/firmante/proyector/aprobador) del grupo concreto.
 *
 * Uso dentro del controller:
 *   $grupo = $this->autorizarGrupo($id);              // creador o miembro
 *   $grupo = $this->autorizarGrupo($id, true);        // solo creador
 *
 * Debe invocarse FUERA del try/catch: lanza HttpException 403 / ModelNotFoundException 404.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-23
 */
trait AutorizaGrupoColaborativo
{
    protected function autorizarGrupo($id, bool $soloCreador = false): MiBandejaTemp
    {
        $grupo = MiBandejaTemp::with(['revisores', 'firmantes', 'proyectores', 'aprobadores'])
            ->findOrFail($id);

        $userId = Auth::id();

        if ((int) $grupo->usua_crea_id === (int) $userId) {
            return $grupo;
        }

        if ($soloCreador) {
            abort(403, 'Solo el creador del grupo puede realizar esta acción');
        }

        $esMiembro = $grupo->revisores->contains('user_id', $userId)
            || $grupo->firmantes->contains('user_id', $userId)
            || $grupo->proyectores->contains('user_id', $userId)
            || $grupo->aprobadores->contains('user_id', $userId);

        abort_unless($esMiembro, 403, 'No eres miembro de este grupo colaborativo');

        return $grupo;
    }
}
