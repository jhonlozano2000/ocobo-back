<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfiArchivo\PlantillaGeneradaRequest;
use App\Http\Resources\OfiArchivo\PlantillaGeneradaResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Services\OfiArchivo\PlantillaGeneradaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Generador de plantillas (Gestión de Archivo → Plantillas de documentos): plantillas institucionales hechas con
 * el editor de Mis Documentos, que todos los usuarios pueden usar al crear un documento.
 */
class OfiArchivoPlantillaGeneradaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private PlantillaGeneradaService $plantillas) {}

    /**
     * Plantillas institucionales, con filtros por nombre, tipo y estado.
     *
     * @queryParam search string Busca en el nombre y la descripción.
     * @queryParam tipo_id integer Tipo de plantilla (lista TipPlanti).
     * @queryParam activo boolean Solo activas o inactivas.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tipo_id' => ['nullable', 'integer'],
            'activo' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $search = trim((string) $request->input('search'));

        $plantillas = OfiArchivoPlantillaGenerada::where('institucional', true)
            ->when($search !== '', fn ($q) => $q->where(fn ($b) => $b->where('nombre', 'like', "%{$search}%")
                ->orWhere('descripcion', 'like', "%{$search}%")))
            ->when($request->filled('tipo_id'), fn ($q) => $q->where('tipo_id', $request->integer('tipo_id')))
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->with(['tipo', 'creador'])
            ->orderBy('nombre')
            ->paginate($request->integer('per_page', 15))
            ->through(fn ($plantilla) => new PlantillaGeneradaResource($plantilla));

        return $this->successResponse($plantillas, 'Plantillas obtenidas exitosamente');
    }

    /** Crea una plantilla institucional en blanco (inactiva hasta que se publique). */
    public function store(PlantillaGeneradaRequest $request): JsonResponse
    {
        $plantilla = $this->plantillas->crear($request->validated(), $request->user(), true);

        return $this->createdResponse(new PlantillaGeneradaResource($plantilla->load(['tipo', 'creador'])), 'Plantilla creada exitosamente');
    }

    /** Nombre, descripción, tipo y estado (publicada o no). */
    public function update(PlantillaGeneradaRequest $request, OfiArchivoPlantillaGenerada $plantilla): JsonResponse
    {
        $this->soloInstitucional($plantilla);
        $plantilla->update($request->validated() + ['user_actualiza_id' => $request->user()->id]);

        return $this->successResponse(new PlantillaGeneradaResource($plantilla->load(['tipo', 'creador'])), 'Plantilla actualizada exitosamente');
    }

    public function destroy(OfiArchivoPlantillaGenerada $plantilla): JsonResponse
    {
        $this->soloInstitucional($plantilla);
        $this->plantillas->eliminar($plantilla);

        return $this->successResponse(null, 'Plantilla eliminada exitosamente');
    }

    /** Documento de trabajo para editar la plantilla en el editor. */
    public function editar(Request $request, OfiArchivoPlantillaGenerada $plantilla): JsonResponse
    {
        $this->soloInstitucional($plantilla);
        $documento = $this->plantillas->abrirEdicion($plantilla, $request->user());

        return $this->successResponse(['documento_id' => $documento->id], 'Plantilla lista para editar');
    }

    /** Copia inactiva de la plantilla, para hacer otra parecida. */
    public function duplicar(Request $request, OfiArchivoPlantillaGenerada $plantilla): JsonResponse
    {
        $this->soloInstitucional($plantilla);
        $copia = $this->plantillas->duplicar($plantilla, $request->user(), true);

        return $this->createdResponse(new PlantillaGeneradaResource($copia->load(['tipo', 'creador'])), 'Plantilla duplicada exitosamente');
    }

    /** Las personales no se gestionan desde aquí (solo su autor, desde Mis Documentos). */
    private function soloInstitucional(OfiArchivoPlantillaGenerada $plantilla): void
    {
        abort_unless($plantilla->institucional, 404);
    }
}
