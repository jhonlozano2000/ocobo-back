<?php

namespace App\Http\Controllers\Calidad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\CalidadOrganigramaRequest;
use App\Http\Requests\Calidad\ListCalidadOrganigramaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Http\Request;
use App\Services\Calidad\CalidadOrganigramaService;

class CalidadOrganigramaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CalidadOrganigramaService $service
    ) {}

    /**
     * Organigrama completo.
     */
    public function index(ListCalidadOrganigramaRequest $request)
    {
        try {
            $filters = $request->all();
            \Log::info('Organigrama index called', ['filters' => $filters]);
            
            $organigrama = $this->service->getAll($filters);
            \Log::info('Organigrama data count', ['count' => is_array($organigrama) ? count($organigrama) : 'collection']);
            \Log::info('Organigrama tipos:', ['tipos' => is_array($organigrama) ? array_count_values(array_column($organigrama, 'tipo')) : 'N/A']);

            return $this->successResponse($organigrama, 'Organigrama obtenido correctamente');
        } catch (\Exception $e) {
            \Log::error('Organigrama index error: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener el organigrama', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo nodo.
     */
    public function store(CalidadOrganigramaRequest $request)
    {
        try {
            $organigrama = $this->service->create($request->validated());

            return $this->successResponse($organigrama, 'Nodo creado correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear el nodo', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un nodo específico.
     */
    public function show(int $id)
    {
        try {
            $organigrama = CalidadOrganigrama::findOrFail($id)->load(['children']);

            return $this->successResponse($organigrama, 'Nodo obtenido correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el nodo', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un nodo.
     */
    public function update(CalidadOrganigramaRequest $request, int $id)
    {
        try {
            $organigrama = $this->service->update($id, $request->validated());

            return $this->successResponse($organigrama, 'Nodo actualizado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el nodo', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un nodo.
     */
    public function destroy(int $id)
    {
        try {
            if (!$this->service->delete($id)) {
                return $this->errorResponse('No se puede eliminar el nodo porque tiene subelementos', null, 400);
            }

            return $this->successResponse(null, 'Nodo eliminado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el nodo', $e->getMessage(), 500);
        }
    }

     /**
     * Lista de dependencias.
     */
    public function listDependencias(Request $request)
    {
        try {
            $filters = $request->all();
            $dependencias = $this->service->getDependencias($filters);

            return $this->successResponse($dependencias, 'Lista de dependencias obtenida');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las dependencias', $e->getMessage(), 500);
        }
    }

    /**
     * Lista de oficinas.
     */
    public function listOficinas(Request $request)
    {
        try {
            $filters = $request->all();
            $oficinas = $this->service->getOficinas($filters);

            return $this->successResponse($oficinas, 'Lista de oficinas con sus respectivos cargos obtenida correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las oficinas', $e->getMessage(), 500);
        }
    }

    /**
     * Estadísticas.
     */
    public function estadisticas()
    {
        try {
            return $this->successResponse(
                $this->service->getStats(),
                'Estadísticas obtenidas exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }
}