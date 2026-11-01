<?php

namespace App\Http\Controllers\Calidad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\CalidadOrganigramaRequest;
use App\Http\Requests\Calidad\ListCalidadOrganigramaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Calidad\CalidadOrganigrama;
use App\Services\Calidad\CalidadOrganigramaService;
use App\Services\ReportesExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            \Log::error('Organigrama index error: '.$e->getMessage());

            return $this->serverErrorResponse('Error al obtener el organigrama', $e->getMessage());
        }
    }

    /**
     * Crea un nuevo nodo.
     */
    public function store(CalidadOrganigramaRequest $request)
    {
        try {
            $organigrama = $this->service->create($request->validated());

            return $this->createdResponse($organigrama, 'Nodo creado correctamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al crear el nodo', $e->getMessage());
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener el nodo', $e->getMessage());
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al actualizar el nodo', $e->getMessage());
        }
    }

    /**
     * Elimina un nodo.
     */
    public function destroy(int $id)
    {
        try {
            if (! $this->service->delete($id)) {
                return $this->warningResponse('No se puede eliminar el nodo porque tiene subelementos');
            }

            return $this->successResponse(null, 'Nodo eliminado correctamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al eliminar el nodo', $e->getMessage());
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener las dependencias', $e->getMessage());
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener las oficinas', $e->getMessage());
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

            return $this->serverErrorResponse('Error al obtener las estadísticas', $e->getMessage());
        }
    }

    /**
     * Exporta el organigrama completo en orden jerárquico (Excel, PDF o CSV).
     */
    public function export(Request $request, ReportesExportService $exportService)
    {
        $request->validate(['format' => 'required|in:excel,pdf,csv']);

        $query = CalidadOrganigrama::query()->orderBy('nom_organico');
        $exportService->validarVolumen($query, $request->format);

        $porPadre = $query->get(['id', 'tipo', 'nom_organico', 'cod_organico', 'parent'])->groupBy(fn ($n) => $n->parent ?? 0);
        $datos = [];
        $recorrer = function (int $padreId, string $padre, int $nivel) use (&$recorrer, &$datos, $porPadre) {
            foreach ($porPadre->get($padreId, []) as $nodo) {
                $datos[] = [
                    'Código' => $nodo->cod_organico,
                    'Nombre' => $nodo->nom_organico,
                    'Tipo' => $nodo->tipo,
                    'Nivel' => $nivel,
                    'Depende de' => $padre,
                ];
                $recorrer($nodo->id, $nodo->nom_organico, $nivel + 1);
            }
        };
        $recorrer(0, '', 0);

        $tipos = array_count_values(array_column($datos, 'Tipo'));
        $data = [
            'titulo' => 'Organigrama',
            'columnas' => ['Código', 'Nombre', 'Tipo', 'Nivel', 'Depende de'],
            'datos' => $datos,
            'total' => count($datos),
            'resumen' => array_filter([
                'Dependencias' => isset($tipos['Dependencia']) ? (string) $tipos['Dependencia'] : null,
                'Oficinas' => isset($tipos['Oficina']) ? (string) $tipos['Oficina'] : null,
                'Cargos' => isset($tipos['Cargo']) ? (string) $tipos['Cargo'] : null,
            ]),
        ];

        $archivo = 'organigrama';

        return match ($request->format) {
            'excel' => response()->download($exportService->exportarExcel($data, $archivo), $archivo.'.xlsx')->deleteFileAfterSend(true),
            'pdf' => response()->download($exportService->exportarPDF($data, $archivo), $archivo.'.pdf')->deleteFileAfterSend(true),
            'csv' => $exportService->exportarCSV($data, $archivo, $data['columnas']),
        };
    }
}
