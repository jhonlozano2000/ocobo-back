<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\StoreGrupoRevisorRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoRevisor;
use Illuminate\Http\Request;

class MiBandejaTempGrupoRevisorController extends Controller
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

            $revisores = $grupo->revisores()->with(['user.cargo', 'cargo'])->get();

            return $this->successResponse($revisores, 'Revisores del grupo');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener revisores', $e->getMessage(), 500);
        }
    }

    public function store(StoreGrupoRevisorRequest $request, $grupoId)
    {
        try {
            $grupo = MiBandejaTemp::find($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            if ($grupo->revisores()->where('user_id', $request->user_id)->exists()) {
                return $this->errorResponse('El usuario ya es revisor en este grupo', null, 422);
            }

            $revisor = MiBandejaTempGrupoRevisor::create([
                'grupo_id' => $grupoId,
                'user_id' => $request->user_id,
                'cargo_id' => $request->cargo_id,
            ]);

            $revisor->load(['user.cargo', 'cargo']);

            return $this->successResponse($revisor, 'Revisor agregado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al agregar revisor', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $grupoId, $id)
    {
        try {
            $revisor = MiBandejaTempGrupoRevisor::where('grupo_id', $grupoId)->find($id);

            if (! $revisor) {
                return $this->errorResponse('Revisor no encontrado', null, 404);
            }

            $revisor->update($request->only(['cargo_id', 'subio_plantilla', 'descargo_plantilla', 'fechor_terminado']));

            $revisor->load(['user.cargo', 'cargo']);

            return $this->successResponse($revisor, 'Revisor actualizado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar revisor', $e->getMessage(), 500);
        }
    }

    public function destroy($grupoId, $id)
    {
        try {
            $revisor = MiBandejaTempGrupoRevisor::where('grupo_id', $grupoId)->find($id);

            if (! $revisor) {
                return $this->errorResponse('Revisor no encontrado', null, 404);
            }

            $revisor->delete();

            return $this->successResponse(null, 'Revisor eliminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar revisor', $e->getMessage(), 500);
        }
    }

    public function marcarTerminado($grupoId, $id)
    {
        try {
            $revisor = MiBandejaTempGrupoRevisor::where('grupo_id', $grupoId)->find($id);

            if (! $revisor) {
                return $this->errorResponse('Revisor no encontrado', null, 404);
            }

            $revisor->update([
                'estado_tarea' => 'cumplido',
                'fechor_terminado' => now(),
                'descargo_plantilla' => true,
            ]);

            return $this->successResponse($revisor, 'Revisor marcado como terminado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al marcar como terminado', $e->getMessage(), 500);
        }
    }
}
