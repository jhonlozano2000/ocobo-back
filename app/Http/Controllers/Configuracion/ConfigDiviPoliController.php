<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ListConfigDiviPoliRequest;
use App\Http\Requests\Configuracion\StoreConfigDiviPoliRequest;
use App\Http\Requests\Configuracion\UpdateConfigDiviPoliRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Configuracion\ConfigDiviPoli;
use App\Services\Configuracion\ConfigDiviPoliService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener el listado', $e->getMessage());
        }
    }

    /**
     * Crea una nueva división política.
     */
    public function store(StoreConfigDiviPoliRequest $request)
    {
        try {
            $model = $this->service->create($request->validated());

            return $this->createdResponse(
                $model->load(['padre', 'children']),
                'División política creada exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al crear', $e->getMessage());
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener', $e->getMessage());
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al actualizar', $e->getMessage());
        }
    }

    /**
     * Elimina una división política.
     */
    public function destroy(int $id)
    {
        try {
            if (! $this->service->delete($id)) {
                return $this->conflictResponse(
                    'No se puede eliminar porque tiene divisiones políticas asociadas'
                );
            }

            return $this->successResponse(null, 'División política eliminada exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al eliminar', $e->getMessage());
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener países', $e->getMessage());
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener departamentos', $e->getMessage());
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener municipios', $e->getMessage());
        }
    }

    /**
     * Divisione políticas por tipo.
     */
    public function listarPorTipo(string $tipo)
    {
        try {
            if (! $this->service->isTipoValido($tipo)) {
                return $this->warningResponse(
                    'Tipo de división política no válido'
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener el listado', $e->getMessage());
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener estadísticas', $e->getMessage());
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener estructura', $e->getMessage());
        }
    }

    /**
     * Carga una división política con sus ancestros (recursivo).
     */
    public function cargarRecursivo(int $id)
    {
        try {
            \Log::info('cargarRecursivo called', ['id' => $id]);
            $result = $this->service->getWithAncestors($id);
            \Log::info('cargarRecursivo result', ['result' => $result]);

            if (! $result) {
                return $this->notFoundResponse('División política no encontrada');
            }

            return $this->successResponse(
                $result,
                'División política con ancestros obtenida exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            \Log::error('cargarRecursivo error: '.$e->getMessage());

            return $this->serverErrorResponse('Error al obtener división política', $e->getMessage());
        }
    }
}
