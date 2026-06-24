<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfiArchivo\StorePlantillaDocumentoRequest;
use App\Http\Requests\OfiArchivo\UpdatePlantillaDocumentoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoPlantillaDocumento;
use App\Services\OfiArchivo\PlantillaDocumentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OfiArchivoPlantillaDocumentoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PlantillaDocumentoService $plantillaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $query = OfiArchivoPlantillaDocumento::with(['creador', 'actualizador']);

            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre_original', 'like', "%{$search}%")
                        ->orWhere('descripcion', 'like', "%{$search}%");
                });
            }

            if ($request->has('extension') && $request->extension !== '') {
                $query->where('extension', $request->extension);
            }

            if ($request->has('activo') && $request->activo !== '') {
                $query->where('activo', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->has('vigente') && filter_var($request->vigente, FILTER_VALIDATE_BOOLEAN)) {
                $query->vigente();
            }

            $perPage = min((int) $request->get('per_page', 15), 100);
            $plantillas = $query->latest()->paginate($perPage);

            return $this->successResponse($plantillas, 'Listado de plantillas obtenido');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado', $e->getMessage(), 500);
        }
    }

    public function store(StorePlantillaDocumentoRequest $request): JsonResponse
    {
        try {
            $plantilla = $this->plantillaService->subirPlantilla(
                $request->safe()->except('archivo'),
                $request->file('archivo')
            );

            return $this->successResponse(
                $plantilla->load('creador'),
                'Plantilla subida exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al subir la plantilla', $e->getMessage(), 500);
        }
    }

    public function show(OfiArchivoPlantillaDocumento $plantilla): JsonResponse
    {
        try {
            return $this->successResponse(
                $plantilla->load(['creador', 'actualizador']),
                'Detalle de plantilla obtenido'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la plantilla', $e->getMessage(), 500);
        }
    }

    public function update(UpdatePlantillaDocumentoRequest $request, OfiArchivoPlantillaDocumento $plantilla): JsonResponse
    {
        try {
            $plantilla = $this->plantillaService->actualizarPlantilla(
                $plantilla,
                $request->safe()->except('archivo'),
                $request->file('archivo')
            );

            return $this->successResponse(
                $plantilla->load(['creador', 'actualizador']),
                'Plantilla actualizada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar la plantilla', $e->getMessage(), 500);
        }
    }

    public function destroy(OfiArchivoPlantillaDocumento $plantilla): JsonResponse
    {
        try {
            $this->plantillaService->eliminarPlantilla($plantilla);

            return $this->successResponse(null, 'Plantilla desactivada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al desactivar la plantilla', $e->getMessage(), 500);
        }
    }

    public function descargar(OfiArchivoPlantillaDocumento $plantilla)
    {
        try {
            $filePath = $this->plantillaService->descargarPlantilla($plantilla);

            if (!file_exists($filePath)) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            return response()->download($filePath, $plantilla->nombre_original);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar la plantilla', $e->getMessage(), 500);
        }
    }

    public function verificarIntegridad(OfiArchivoPlantillaDocumento $plantilla): JsonResponse
    {
        try {
            $integridad = $this->plantillaService->verificarIntegridad($plantilla);

            return $this->successResponse([
                'plantilla_id' => $plantilla->id,
                'nombre_original' => $plantilla->nombre_original,
                'hash_actual' => $plantilla->hash_seguridad,
                'integridad_ok' => $integridad,
            ], $integridad ? 'Integridad verificada correctamente' : 'El archivo ha sido modificado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al verificar integridad', $e->getMessage(), 500);
        }
    }

    public function estadisticas(): JsonResponse
    {
        try {
            $total = OfiArchivoPlantillaDocumento::count();
            $activas = OfiArchivoPlantillaDocumento::activas()->count();
            $inactivas = $total - $activas;
            $vencidas = OfiArchivoPlantillaDocumento::where('activo', true)
                ->whereNotNull('fecha_vencimiento')
                ->where('fecha_vencimiento', '<', now())
                ->count();
            $proximasVencer = OfiArchivoPlantillaDocumento::where('activo', true)
                ->whereNotNull('fecha_vencimiento')
                ->whereBetween('fecha_vencimiento', [now(), now()->addDays(30)])
                ->count();

            $stats = [
                'total' => $total,
                'activas' => $activas,
                'inactivas' => $inactivas,
                'vencidas' => $vencidas,
                'proximas_vencer' => $proximasVencer,
            ];

            return $this->successResponse($stats, 'Estadísticas de plantillas obtenidas');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }
}
