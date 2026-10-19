<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteTomo;
use App\Models\OfiArchivo\OfiArchivoUbicacion;
use App\Services\OfiArchivo\ConfiguracionArchivo;
use App\Services\OfiArchivo\HojaControlService;
use App\Services\OfiArchivo\UbicacionTopograficaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * B20 (diseño de expedientes §5): catálogo topográfico del archivo (depósito → estante →
 * entrepaño → caja) y ubicación de los expedientes físicos e híbridos en él.
 */
class OfiArchivoUbicacionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private UbicacionTopograficaService $ubicaciones) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'archivo' => 'nullable|in:gestion,central,historico',
            'tipo' => 'nullable|in:'.implode(',', OfiArchivoUbicacion::NIVELES),
            'vigente' => 'nullable|boolean',
        ]);

        return $this->successResponse([
            'con_catalogo' => ConfiguracionArchivo::ubicacionConCatalogo(),
            'ubicaciones' => $this->ubicaciones->catalogo(array_filter($filtros, fn ($v) => $v !== null)),
        ], 'Catálogo de ubicaciones');
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'parent_id' => 'nullable|integer',
            'archivo' => 'nullable|in:gestion,central,historico',
            'codigo' => 'required|string|max:30',
            'nombre' => 'nullable|string|max:150',
            'capacidad' => 'nullable|integer|min:1|max:10000',
        ]);

        $nodo = DB::transaction(fn () => $this->ubicaciones->crear($datos));

        return $this->createdResponse($nodo, "{$nodo->ruta} creada");
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => 'sometimes|required|string|max:30',
            'nombre' => 'nullable|string|max:150',
            'capacidad' => 'nullable|integer|min:1|max:10000',
            'activo' => 'sometimes|boolean',
        ]);

        $nodo = DB::transaction(fn () => $this->ubicaciones->actualizar(OfiArchivoUbicacion::lockForUpdate()->findOrFail($id), $datos));

        return $this->successResponse($nodo, "{$nodo->ruta} actualizada");
    }

    public function destroy(int $id): JsonResponse
    {
        DB::transaction(fn () => $this->ubicaciones->eliminar(OfiArchivoUbicacion::lockForUpdate()->findOrFail($id)));

        return $this->successResponse(null, 'Ubicación eliminada');
    }

    /** Expedientes por ubicar: sin caja del catálogo o en una caja de otro archivo. */
    public function pendientes(Request $request): JsonResponse
    {
        $request->validate([
            'fase' => 'nullable|in:gestion,central,historico',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $pagina = $this->ubicaciones->pendientes(OfiArchivoExpediente::visiblesPara(Auth::user()))
            ->when($request->fase, fn ($q, $fase) => $q->where('fase', $fase))
            ->when($request->search, fn ($q, $texto) => $q->where(fn ($q) => $q->where('numero_expediente', 'like', "%{$texto}%")
                ->orWhere('nombre_expediente', 'like', "%{$texto}%")))
            ->with('ubicacion:id,ruta,archivo')
            ->orderBy('fase')->orderBy('numero_expediente')
            ->paginate((int) ($request->per_page ?: 20), [
                'id', 'numero_expediente', 'nombre_expediente', 'estado', 'fase', 'soporte', 'ubicacion_id', 'ubicacion_fisica', 'caja', 'carpeta',
            ]);

        return $this->successResponse($pagina, 'Expedientes por ubicar');
    }

    /**
     * Ubica el expediente y sus tomos; vale también cerrado o transferido (traslados y recepción
     * en el archivo central).
     */
    public function ubicarExpediente(Request $request, int $id, HojaControlService $hoja): JsonResponse
    {
        $datos = $request->validate([
            'ubicacion_id' => 'nullable|integer',
            'ubicacion_fisica' => 'nullable|string|max:200',
            'caja' => 'nullable|string|max:50',
            'carpeta' => 'nullable|string|max:50',
            'tomos' => 'nullable|array|max:500',
            'tomos.*.id' => 'required|integer|distinct',
            'tomos.*.ubicacion_id' => 'nullable|integer',
            'tomos.*.ubicacion_fisica' => 'nullable|string|max:200',
            'tomos.*.caja' => 'nullable|string|max:50',
            'tomos.*.carpeta' => 'nullable|string|max:50',
        ]);

        return DB::transaction(function () use ($datos, $id, $hoja) {
            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->lockForUpdate()->findOrFail($id);
            if ($expediente->estado === 'eliminado') {
                return $this->warningResponse('El expediente fue eliminado; no se reubica.');
            }
            if ($expediente->soporte === 'electronico') {
                return $this->warningResponse('El expediente electrónico no tiene ubicación física.');
            }

            $campos = UbicacionTopograficaService::campos();
            $expediente->update($this->ubicaciones->resolver(array_intersect_key($datos, array_flip($campos)), $expediente->only($campos), $expediente));
            foreach ($datos['tomos'] ?? [] as $i => $datosTomo) {
                $tomo = OfiArchivoExpedienteTomo::where('expediente_id', $expediente->id)->find($datosTomo['id']);
                abort_unless($tomo, 404, 'El tomo no pertenece al expediente');
                try {
                    $hoja->ubicarTomo($tomo->setRelation('expediente', $expediente), $datosTomo);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($mensajes, $campo) => [
                        "tomos.{$i}.{$campo}" => array_map(fn ($m) => "Tomo {$tomo->numero}: {$m}", $mensajes),
                    ])->all());
                }
            }

            return $this->successResponse([
                'expediente' => $expediente->fresh()->only(['id', 'numero_expediente', 'fase', 'ubicacion_id', 'ubicacion_fisica', 'caja', 'carpeta']),
                'tomos' => HojaControlService::disponible() ? $hoja->tomos($expediente) : [],
            ], "Expediente {$expediente->numero_expediente} ubicado");
        });
    }
}
