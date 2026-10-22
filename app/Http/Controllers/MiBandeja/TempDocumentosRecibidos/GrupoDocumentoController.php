<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Grupo colaborativo de un documento de Mis Documentos (solo lectura). El grupo se crea con el mismo flujo de
 * Grupos colaborativos (POST /grupos-colaborativos con `documento_id` y luego sus miembros); queda en
 * `mi_bandeja_temp` y el acceso de sus miembros al documento sale de esa membresía (Documento::rolEnGrupo).
 */
class GrupoDocumentoController extends Controller
{
    use ApiResponseTrait;

    private const ROLES = ['proyectores', 'revisores', 'aprobadores', 'firmantes'];

    private const PERMISO_GRUPOS = 'Mi Bandeja - Grupos Colaborativos -> ';

    /**
     * El grupo vigente del documento (o null) y si el usuario puede crearlo: dueño del documento y con los
     * permisos de Grupos colaborativos para crear grupos y gestionar sus miembros.
     */
    public function show(Request $request, Documento $documento): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse([
            'puede_gestionar' => $user->can('gestionarUsuarios', $documento)
                && $user->can(self::PERMISO_GRUPOS.'Crear')
                && $user->can(self::PERMISO_GRUPOS.'Gestionar Miembros'),
            'grupo' => ($grupo = $documento->grupoVigente()) ? $this->resumen($grupo) : null,
        ]);
    }

    private function resumen(MiBandejaTemp $grupo): array
    {
        $grupo->load(array_map(fn ($rol) => "{$rol}.user:id,nombres,apellidos", self::ROLES));
        $radicado = $grupo->radicado;

        return [
            'id' => $grupo->id,
            'nombre' => $grupo->nombre,
            'asunto' => $grupo->asunto,
            'descripcion' => $grupo->descripcion,
            'estado' => $grupo->estado,
            'estado_grupo' => $grupo->estado_grupo,
            'radicado' => $radicado ? [
                'id' => $radicado->id,
                'tipo' => $grupo->radicado_tipo,
                'numero' => $radicado->num_radicado ?? null,
            ] : null,
            'miembros' => collect(self::ROLES)->mapWithKeys(fn ($rol) => [
                $rol => $grupo->{$rol}
                    ->sortBy($rol === 'firmantes' ? 'orden_firma' : 'id')
                    ->map(fn ($m) => [
                        'user_id' => $m->user_id,
                        'nombre' => $m->user ? trim("{$m->user->nombres} {$m->user->apellidos}") : null,
                        'estado_tarea' => $m->estado_tarea ?? 'pendiente',
                    ])
                    ->values(),
            ]),
        ];
    }
}
