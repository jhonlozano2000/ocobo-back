<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\ClasificacionDocumental\StoreClasificacionDocumentalRequest;
use App\Http\Requests\ClasificacionDocumental\UpdateClasificacionDocumentalRequest;
use App\Http\Requests\ClasificacionDocumental\ImportarTRDRequest;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\ClasificacionDocumental\TRDService;

class ClasificacionDocumentalTRDController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TRDService $service
    ) {}

    public function index(Request $request)
    {
        try {
            $filters = $request->validated();
            $trd = $this->service->getAll($filters);

            return $this->successResponse($trd, 'TRD obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las TRD', $e->getMessage(), 500);
        }
    }

    public function store(StoreClasificacionDocumentalRequest $request)
    {
        try {
            $trd = $this->service->create($request->validated());

            return $this->successResponse($trd, 'Elemento TRD creado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear el elemento TRD', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $trd = ClasificacionDocumentalTRD::with([
                'children',
                'dependencia',
                'parent',
                'parent.parent'
            ])->find($id);

            if (!$trd) {
                return $this->errorResponse('Elemento TRD no encontrado', null, 404);
            }

            return $this->successResponse($trd, 'Elemento TRD obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el elemento TRD', $e->getMessage(), 500);
        }
    }

    public function update(UpdateClasificacionDocumentalRequest $request, $id)
    {
        try {
            $trd = $this->service->update($id, $request->validated());

            if (!$trd) {
                return $this->errorResponse('Elemento TRD no encontrado', null, 404);
            }

            return $this->successResponse($trd, 'Elemento TRD actualizado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el elemento TRD', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            if (!$this->service->delete($id)) {
                return $this->errorResponse('No se puede eliminar el elemento TRD', 'El elemento tiene elementos hijos asociados', 422);
            }

            return $this->successResponse(null, 'Elemento TRD eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el elemento TRD', $e->getMessage(), 500);
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
            return $this->errorResponse('Error al obtener estadísticas totales', $e->getMessage(), 500);
        }
    }

    public function estadistica($id)
    {
        try {
            $dependencia = CalidadOrganigrama::find($id);

            if (!$dependencia) {
                return $this->errorResponse('Dependencia no encontrada', null, 404);
            }

            return $this->successResponse(
                array_merge(
                    ['dependencia' => $dependencia],
                    $this->service->getStatsByDependencia($id)
                ),
                'Estadísticas obtenidas exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }

    public function listarPorDependencia($id)
    {
        try {
            $dependencia = CalidadOrganigrama::find($id);

            if (!$dependencia) {
                return $this->errorResponse('Dependencia no encontrada', null, 404);
            }

            $elementos = ClasificacionDocumentalTRD::where('dependencia_id', $id)
                ->whereNull('parent')
                ->with([
                    'children',
                    'dependencia',
                    'children.parent',
                    'children.parent.parent'
                ])
                ->orderBy('cod', 'asc')
                ->get();

            return $this->successResponse([
                'dependencia' => $dependencia,
                'elementos' => $elementos,
                'total_elementos' => ClasificacionDocumentalTRD::where('dependencia_id', $id)->count()
            ], 'Elementos TRD obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener elementos TRD', $e->getMessage(), 500);
        }
    }

    public function descargarPlantilla()
    {
        try {
            $rutaArchivo = 'plantillas/Ocobo - Plantilla TRD.xlsx';

            if (!Storage::exists($rutaArchivo)) {
                return $this->errorResponse('Plantilla no encontrada', null, 404);
            }

            return Storage::download($rutaArchivo, 'Ocobo - Plantilla TRD.xlsx');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar la plantilla', $e->getMessage(), 500);
        }
    }

    public function importarTRD(ImportarTRDRequest $request)
    {
        try {
            $filePath = $this->service->importFromExcel($request->all(), $request->file('archivo'));

            return $this->successResponse($filePath, 'TRD importada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al importar TRD', $e->getMessage(), 500);
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
            return $this->errorResponse('Error al obtener estadísticas por dependencias', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene los días de vencimiento para una clasificación TRD específica.
     * Útil para el formulario de radicación.
     */
    public function getDiasVencimiento(int $id)
    {
        try {
            $clasificacion = ClasificacionDocumentalTRD::find($id);

            if (!$clasificacion) {
                return $this->errorResponse('Elemento TRD no encontrado', null, 404);
            }

            $info = $clasificacion->getInfoDiasVencimiento();

            return $this->successResponse([
                'clasificacion_id' => $id,
                'clasificacion_nombre' => $clasificacion->nom,
                'clasificacion_tipo' => $clasificacion->tipo,
                'dias_vencimiento' => $info['dias'],
                'fuente' => $info['fuente'],
                'jerarquia' => $info['jerarquia']
            ], 'Días de vencimiento obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener días de vencimiento', $e->getMessage(), 500);
        }
    }
}