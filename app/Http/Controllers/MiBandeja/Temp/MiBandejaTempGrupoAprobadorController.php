<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\StoreGrupoAprobadorRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoAprobador;
use Illuminate\Http\Request;

class MiBandejaTempGrupoAprobadorController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Mi Bandeja - Grupos Colaborativos -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Gestionar Miembros')->only(['store', 'destroy', 'update']);
    }

    public function index($grupoId)
    {
        try {
            $grupo = MiBandejaTemp::find($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $aprobadores = $grupo->aprobadores()->with(['user.cargo', 'cargo'])->get();

            return $this->successResponse($aprobadores, 'Aprobadores del grupo');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener aprobadores', $e->getMessage(), 500);
        }
    }

    public function store(StoreGrupoAprobadorRequest $request, $grupoId)
    {
        try {
            $grupo = MiBandejaTemp::find($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            if ($grupo->aprobadores()->where('user_id', $request->user_id)->exists()) {
                return $this->errorResponse('El usuario ya es aprobador en este grupo', null, 422);
            }

            $aprobador = MiBandejaTempGrupoAprobador::create([
                'grupo_id' => $grupoId,
                'user_id' => $request->user_id,
                'cargo_id' => $request->cargo_id,
            ]);

            $aprobador->load(['user.cargo', 'cargo']);

            return $this->successResponse($aprobador, 'Aprobador agregado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al agregar aprobador', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $grupoId, $id)
    {
        try {
            $aprobador = MiBandejaTempGrupoAprobador::where('grupo_id', $grupoId)->find($id);

            if (! $aprobador) {
                return $this->errorResponse('Aprobador no encontrado', null, 404);
            }

            $aprobador->update($request->only(['cargo_id', 'subio_plantilla', 'descargo_plantilla', 'fechor_terminado']));

            $aprobador->load(['user.cargo', 'cargo']);

            return $this->successResponse($aprobador, 'Aprobador actualizado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar aprobador', $e->getMessage(), 500);
        }
    }

    public function destroy($grupoId, $id)
    {
        try {
            $aprobador = MiBandejaTempGrupoAprobador::where('grupo_id', $grupoId)->find($id);

            if (! $aprobador) {
                return $this->errorResponse('Aprobador no encontrado', null, 404);
            }

            $aprobador->delete();

            return $this->successResponse(null, 'Aprobador eliminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar aprobador', $e->getMessage(), 500);
        }
    }

    public function marcarTerminado($grupoId, $id)
    {
        try {
            $aprobador = MiBandejaTempGrupoAprobador::where('grupo_id', $grupoId)->find($id);

            if (! $aprobador) {
                return $this->errorResponse('Aprobador no encontrado', null, 404);
            }

            $aprobador->update([
                'estado_tarea' => 'cumplido',
                'fechor_terminado' => now(),
                'descargo_plantilla' => true,
            ]);

            return $this->successResponse($aprobador, 'Aprobador marcado como terminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al marcar como terminado', $e->getMessage(), 500);
        }
    }
}
