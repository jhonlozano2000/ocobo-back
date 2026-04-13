<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTVD;
use Illuminate\Http\Request;
use App\Services\ClasificacionDocumental\TVDService;

class ClasificacionDocumentalTVDController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TVDService $service
    ) {}

    public function index(Request $request)
    {
        try {
            $filters = $request->all();
            $tvd = $this->service->getAll($filters);

            return $this->successResponse($tvd, 'TVD obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las TVD', $e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $tvd = $this->service->create($request->all());

            return $this->successResponse($tvd, 'Elemento TVD creado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear el elemento TVD', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $tvd = ClasificacionDocumentalTVD::with(['children', 'dependencia', 'parent'])->find($id);

            if (!$tvd) {
                return $this->errorResponse('Elemento TVD no encontrado', null, 404);
            }

            return $this->successResponse($tvd, 'Elemento TVD obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el elemento TVD', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $tvd = $this->service->update($id, $request->all());

            if (!$tvd) {
                return $this->errorResponse('Elemento TVD no encontrado', null, 404);
            }

            return $this->successResponse($tvd, 'Elemento TVD actualizado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el elemento TVD', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            if (!$this->service->delete($id)) {
                return $this->errorResponse('No se puede eliminar el elemento TVD porque tiene hijos o no existe', null, 422);
            }

            return $this->successResponse(null, 'Elemento TVD eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el elemento TVD', $e->getMessage(), 500);
        }
    }

    public function estadisticas()
    {
        try {
            return $this->successResponse(
                $this->service->getTotalStats(),
                'Estadísticas totales obtenidas exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas totales', $e->getMessage(), 500);
        }
    }
}