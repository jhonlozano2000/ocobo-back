<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\StoreConfigDiviPoliRequest;
use App\Http\Requests\Configuracion\UpdateConfigDiviPoliRequest;
use App\Http\Requests\Configuracion\ListConfigDiviPoliRequest;
use App\Models\Configuracion\ConfigDiviPoli;
use Illuminate\Http\Request;
use App\Services\Configuracion\ConfigDiviPoliService;

class ConfigDiviPoliController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ConfigDiviPoliService $service
    ) {}

    /**
     * Listado de divisiones políticas.
     */
    public function index(ListConfigDiviPoliRequest $request)
    {
        try {
            $filters = $request->validated();
            $data = $this->service->getAll($filters);

            return $this->successResponse($data, 'Listado de divisiones políticas obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva división política.
     */
    public function store(StoreConfigDiviPoliRequest $request)
    {
        try {
            $model = $this->service->create($request->validated());

            return $this->successResponse(
                $model->load(['padre', 'children']),
                'División política creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una división política específica.
     */
    public function show(ConfigDiviPoli $configDiviPoli)
    {
        try {
            return $this->successResponse(
                $configDiviPoli->load(['padre', 'children']),
                'División política encontrada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una división política.
     */
    public function update(UpdateConfigDiviPoliRequest $request, int $id)
    {
        try {
            $model = $this->service->update($id, $request->validated());

            return $this->successResponse(
                $model->load(['padre', 'children']),
                'División política actualizada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una división política.
     */
    public function destroy(int $id)
    {
        try {
            if (!$this->service->delete($id)) {
                return $this->errorResponse(
                    'No se puede eliminar porque tiene divisiones políticas asociadas',
                    null,
                    409
                );
            }

            return $this->successResponse(null, 'División política eliminada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar', $e->getMessage(), 500);
        }
    }

    /**
     * Listado de países.
     */
    public function paises()
    {
        try {
            return $this->successResponse(
                $this->service->getPaises(),
                'Listado de países obtenido exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener países', $e->getMessage(), 500);
        }
    }

    /**
     * Departamentos de un país.
     */
    public function departamentos(int $paisId)
    {
        try {
            return $this->successResponse(
                $this->service->getDepartamentos($paisId),
                'Listado de departamentos obtenido exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener departamentos', $e->getMessage(), 500);
        }
    }

    /**
     * Municipios de un departamento.
     */
    public function municipios(int $departamentoId)
    {
        try {
            return $this->successResponse(
                $this->service->getMunicipios($departamentoId),
                'Listado de municipios obtenido exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener municipios', $e->getMessage(), 500);
        }
    }

    /**
     * Divisione políticas por tipo.
     */
    public function listarPorTipo(string $tipo)
    {
        try {
            if (!$this->service->isTipoValido($tipo)) {
                return $this->errorResponse(
                    'Tipo de división política no válido',
                    null,
                    400
                );
            }

            return $this->successResponse(
                ConfigDiviPoli::where('tipo', $tipo)
                    ->with(['padre', 'children'])
                    ->orderBy('nombre', 'asc')
                    ->get(),
                'Listado de divisiones políticas obtenido exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado', $e->getMessage(), 500);
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
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }

    /**
     * Estructura jerárquica completa.
     */
    public function diviPoliCompleta()
    {
        try {
            return $this->successResponse(
                $this->service->getHierarchy(),
                'Estructura jerárquica de países obtenida exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estructura', $e->getMessage(), 500);
        }
    }
}