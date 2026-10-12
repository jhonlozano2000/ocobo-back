<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClasificacionDocumental\ImportarTRDRequest;
use App\Http\Requests\ClasificacionDocumental\StoreClasificacionDocumentalRequest;
use App\Http\Requests\ClasificacionDocumental\UpdateClasificacionDocumentalRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Services\ClasificacionDocumental\TRDService;
use App\Services\MiBandeja\MiBandejaFiltroService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ClasificacionDocumentalTRDController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TRDService $service
    ) {}

    public function index(Request $request)
    {
        try {
            $filters = $request->only([
                'dependencia_id', 'nombre', 'cod_agrupa_docu', 'codigo', 'estado',
                'per_page', 'page', 'search', 'sort', 'order',
            ]);
            $trd = $this->service->getAll($filters);

            return $this->successResponse($trd, 'TRD obtenidas exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener las TRD', $e->getMessage());
        }
    }

    public function store(StoreClasificacionDocumentalRequest $request)
    {
        try {
            $dependenciaId = $request->input('dependencia_id');
            if (! $this->usuarioPuedeGestionarDependencia($dependenciaId)) {
                return $this->forbiddenResponse('No tiene permisos para crear elementos TRD en otra dependencia');
            }

            $trd = $this->service->create($request->validated());

            return $this->createdResponse($trd, 'Elemento TRD creado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al crear el elemento TRD', $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $trd = ClasificacionDocumentalTRD::with([
                'children',
                'dependencia',
                'parent',
                'parent.parent',
            ])->find($id);

            if (! $trd) {
                return $this->notFoundResponse('Elemento TRD no encontrado');
            }

            return $this->successResponse($trd, 'Elemento TRD obtenido exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener el elemento TRD', $e->getMessage());
        }
    }

    public function update(UpdateClasificacionDocumentalRequest $request, $id)
    {
        try {
            $trd = ClasificacionDocumentalTRD::find($id);
            if (! $trd) {
                return $this->notFoundResponse('Elemento TRD no encontrado');
            }

            if (! $this->usuarioPuedeGestionarDependencia($trd->dependencia_id)) {
                return $this->forbiddenResponse('No tiene permisos para modificar elementos TRD de otra dependencia');
            }

            $trd = $this->service->update($id, $request->validated());

            return $this->successResponse($trd, 'Elemento TRD actualizado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al actualizar el elemento TRD', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $trd = ClasificacionDocumentalTRD::find($id);
            if (! $trd) {
                return $this->notFoundResponse('Elemento TRD no encontrado');
            }

            if (! $this->usuarioPuedeGestionarDependencia($trd->dependencia_id)) {
                return $this->forbiddenResponse('No tiene permisos para eliminar elementos TRD de otra dependencia');
            }

            if (! $this->service->delete($id)) {
                return $this->warningResponse('No se puede eliminar el elemento TRD porque tiene elementos hijos asociados');
            }

            return $this->successResponse(null, 'Elemento TRD eliminado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al eliminar el elemento TRD', $e->getMessage());
        }
    }

    public function estadisticasTotales()
    {
        try {
            return $this->successResponse(
                $this->service->getTotalStats(),
                'Estadísticas totales obtenidas exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener estadísticas totales', $e->getMessage());
        }
    }

    public function estadistica($id)
    {
        try {
            $dependencia = CalidadOrganigrama::find($id);

            if (! $dependencia) {
                return $this->notFoundResponse('Dependencia no encontrada');
            }

            return $this->successResponse(
                array_merge(
                    ['dependencia' => $dependencia],
                    $this->service->getStatsByDependencia($id)
                ),
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

    public function listarPorDependencia(Request $request, $id)
    {
        try {
            $dependencia = CalidadOrganigrama::find($id);

            if (! $dependencia) {
                return $this->notFoundResponse('Dependencia no encontrada');
            }

            $elementos = ClasificacionDocumentalTRD::where('dependencia_id', $id)
                ->whereNull('parent')
                // M09-07: ?vigente=1 limita el árbol a la versión aprobada (clasificación de expedientes)
                ->when($request->boolean('vigente'), fn ($q) => $q->whereHas('versionTRD', fn ($v) => $v->where('estado_version', 'ACTIVO')))
                ->with([
                    'children',
                    'dependencia',
                    'children.parent',
                    'children.parent.parent',
                ])
                ->orderBy('cod', 'asc')
                ->get();

            return $this->successResponse([
                'dependencia' => $dependencia,
                'elementos' => $elementos,
                'total_elementos' => ClasificacionDocumentalTRD::where('dependencia_id', $id)->count(),
            ], 'Elementos TRD obtenidos exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener elementos TRD', $e->getMessage());
        }
    }

    public function descargarPlantilla()
    {
        try {
            $rutaArchivo = 'plantillas/Ocobo - Plantilla TRD.xlsx';

            if (! Storage::exists($rutaArchivo)) {
                return $this->notFoundResponse('Plantilla no encontrada');
            }

            return Storage::download($rutaArchivo, 'Ocobo - Plantilla TRD.xlsx');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al descargar la plantilla', $e->getMessage());
        }
    }

    public function importarTRD(ImportarTRDRequest $request)
    {
        try {
            $filePath = $this->service->importFromExcel($request->all(), $request->file('archivo'));

            return $this->successResponse($filePath, 'TRD importada exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al importar TRD', $e->getMessage());
        }
    }

    public function estadisticasPorDependencias(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $sortField = $request->get('sort', 'total_elementos');
            $sortOrder = $request->get('order', 'desc');

            $query = ClasificacionDocumentalTRD::selectRaw('
                dependencia_id,
                COUNT(*) as total_elementos,
                SUM(CASE WHEN tipo = "Serie" THEN 1 ELSE 0 END) as series,
                SUM(CASE WHEN tipo = "SubSerie" THEN 1 ELSE 0 END) as subseries,
                SUM(CASE WHEN tipo = "TipoDocumento" THEN 1 ELSE 0 END) as tipos_documento
            ')
                ->groupBy('dependencia_id')
                ->with('dependencia:id,nom_organico,cod_organico');

            if (in_array($sortField, ['total_elementos', 'series', 'subseries', 'tipos_documento'])) {
                $query->orderBy($sortField, $sortOrder);
            }

            $estadisticas = $query->paginate($perPage);

            $estadisticas->getCollection()->transform(function ($item) {
                $total = $item->total_elementos;
                $item->porcentaje_series = $total > 0 ? round(($item->series / $total) * 100, 2) : 0;
                $item->porcentaje_subseries = $total > 0 ? round(($item->subseries / $total) * 100, 2) : 0;
                $item->porcentaje_tipos_documento = $total > 0 ? round(($item->tipos_documento / $total) * 100, 2) : 0;

                return $item;
            });

            return $this->successResponse($estadisticas, 'Estadísticas por dependencias obtenidas exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener estadísticas por dependencias', $e->getMessage());
        }
    }

    /**
     * Obtiene los días de vencimiento para una clasificación TRD específica.
     * Útil para el formulario de radicación.
     * Solo retorna días configurados explícitamente - no usa default.
     */
    public function getDiasVencimiento(int $id)
    {
        try {
            $clasificacion = ClasificacionDocumentalTRD::find($id);

            if (! $clasificacion) {
                return $this->notFoundResponse('Elemento TRD no encontrado');
            }

            $info = $clasificacion->getInfoDiasVencimientoConfigurado();

            return $this->successResponse([
                'clasificacion_id' => $id,
                'clasificacion_nombre' => $clasificacion->nom,
                'clasificacion_tipo' => $clasificacion->tipo,
                'dias_vencimiento' => $info['dias'],
                'fuente' => $info['fuente'],
                'jerarquia' => $info['jerarquia'],
            ], 'Días de vencimiento obtenidos exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener días de vencimiento', $e->getMessage());
        }
    }

    /**
     * M10-06: Valida si el usuario tiene alcance para gestionar la TRD de la dependencia.
     * Roles de administración y Jefe de Archivo tienen alcance global; otros usuarios
     * están restringidos a la dependencia activa asignada a su cargo.
     */
    private function usuarioPuedeGestionarDependencia(?int $dependenciaId): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['Administrador', 'Jefe de Archivo']) || $user->can('TRD -> Ver Todos')) {
            return true;
        }

        if (! $dependenciaId) {
            return true;
        }

        $miDependencia = MiBandejaFiltroService::getDependenciaDelUsuario($user->id);
        if (! $miDependencia) {
            return false;
        }

        return (int) $miDependencia->id === (int) $dependenciaId;
    }
}
