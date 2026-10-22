<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfiArchivo\PlantillaGeneradaRequest;
use App\Http\Resources\OfiArchivo\PlantillaGeneradaResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Configuracion\ConfigLista;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\OfiArchivo\OfiArchivoPlantillaDocumento;
use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Services\MiBandeja\TempDocumentosRecibidos\DocumentoImportService;
use App\Services\OfiArchivo\PlantillaDocumentoService;
use App\Services\OfiArchivo\PlantillaGeneradaService;
use App\Services\OfiArchivo\VariablesPlantillaService;
use Database\Seeders\Configuracion\TiposPlantillaSeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Plantillas para crear documentos en Mis Documentos:
 * - generadas con el editor: las institucionales publicadas y las personales del usuario («Guardar como
 *   plantilla»), que llenan solas la fecha, la ciudad y los datos del usuario y de la entidad;
 * - cargadas en Gestión de Archivo (archivos): se descargan, y las de Word o texto se pueden abrir en el editor.
 */
class PlantillaController extends Controller
{
    use ApiResponseTrait;

    /** Extensiones de las plantillas cargadas que el editor sabe importar. */
    public const EXTENSIONES_EDITABLES = ['docx', 'doc', 'html', 'htm', 'txt'];

    public function __construct(private PlantillaGeneradaService $plantillas) {}

    /**
     * Plantillas disponibles para el usuario, con los tipos para filtrarlas y las variables que se pueden
     * insertar en una plantilla.
     *
     * @response 200 {"data": {"generadas": [], "cargadas": [], "tipos": [{"id": 1, "nombre": "Acta"}], "variables": [{"clave": "fecha", "etiqueta": "Fecha"}]}}
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $generadas = OfiArchivoPlantillaGenerada::visiblesPara($userId)
            ->with('tipo')
            ->orderByDesc('institucional')
            ->orderBy('nombre')
            ->get();

        $cargadas = OfiArchivoPlantillaDocumento::vigente()
            ->orderBy('nombre_original')
            ->get()
            ->map(fn (OfiArchivoPlantillaDocumento $plantilla) => [
                'id' => $plantilla->id,
                'nombre' => pathinfo($plantilla->nombre_original, PATHINFO_FILENAME),
                'descripcion' => $plantilla->descripcion,
                'extension' => strtolower((string) $plantilla->extension),
                'peso' => $plantilla->peso_formateado,
                'version' => $plantilla->version,
                'tipo_id' => $plantilla->categoria_id,
                'editable' => in_array(strtolower((string) $plantilla->extension), self::EXTENSIONES_EDITABLES, true),
            ]);

        $tipos = ConfigLista::where('cod', TiposPlantillaSeed::COD_LISTA)->first()?->detalles()
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get(['id', 'nombre']) ?? collect();

        return $this->successResponse([
            'generadas' => PlantillaGeneradaResource::collection($generadas)->resolve($request),
            'cargadas' => $cargadas,
            'tipos' => $tipos,
            'variables' => app(VariablesPlantillaService::class)->catalogo(),
        ], 'Plantillas obtenidas exitosamente');
    }

    /**
     * «Guardar como plantilla»: plantilla personal con el contenido y la página de un documento al que el usuario
     * tiene acceso (sin comentarios ni cambios sugeridos).
     *
     * @bodyParam documento_id integer required Documento de origen.
     */
    public function store(PlantillaGeneradaRequest $request): JsonResponse
    {
        $request->validate(['documento_id' => ['required', 'integer']]);
        $documento = Documento::with('contenido')->findOrFail($request->integer('documento_id'));
        $this->authorize('ver', $documento);

        $plantilla = $this->plantillas->crearDesdeDocumento($documento, $request->validated(), $request->user());

        return $this->createdResponse(new PlantillaGeneradaResource($plantilla->load('tipo')), 'Plantilla guardada exitosamente');
    }

    /** Nombre, descripción y tipo de una plantilla personal. */
    public function update(PlantillaGeneradaRequest $request, OfiArchivoPlantillaGenerada $plantilla): JsonResponse
    {
        $this->soloPersonal($request, $plantilla);
        $plantilla->update($request->safe()->only(['nombre', 'descripcion', 'tipo_id']));

        return $this->successResponse(new PlantillaGeneradaResource($plantilla->load('tipo')), 'Plantilla actualizada exitosamente');
    }

    public function destroy(Request $request, OfiArchivoPlantillaGenerada $plantilla): JsonResponse
    {
        $this->soloPersonal($request, $plantilla);
        $this->plantillas->eliminar($plantilla);

        return $this->successResponse(null, 'Plantilla eliminada exitosamente');
    }

    /** Documento de trabajo para editar una plantilla personal en el editor. */
    public function editar(Request $request, OfiArchivoPlantillaGenerada $plantilla): JsonResponse
    {
        $this->soloPersonal($request, $plantilla);
        $documento = $this->plantillas->abrirEdicion($plantilla, $request->user());

        return $this->successResponse(['documento_id' => $documento->id], 'Plantilla lista para editar');
    }

    /**
     * Pasa a la plantilla lo editado en su documento de trabajo (botón «Guardar plantilla» del editor). Las
     * institucionales piden el permiso de edición del Generador.
     */
    public function guardar(Request $request, Documento $documento): JsonResponse
    {
        $plantilla = $documento->plantillaGenerada;
        abort_unless($plantilla && $documento->user_id === $request->user()->id, 404);
        $this->plantillas->autorizar($request->user(), $plantilla);

        $plantilla = $this->plantillas->guardarDesdeDocumento($documento->load('contenido'), $request->user());

        return $this->successResponse(new PlantillaGeneradaResource($plantilla->load('tipo')), 'Plantilla guardada exitosamente');
    }

    /**
     * Combinación de correspondencia: las variables con su valor para el documento (datos del usuario, de la
     * entidad y del radicado recibido al que responde). El editor las muestra en la vista previa y, al
     * finalizar, las cambia por el texto.
     *
     * @response 200 {"data": {"radicado": {"id": 7, "num_radicado": "2026-R-0001", "asunto": "Derecho de petición"},
     *   "variables": [{"clave": "radicado_numero", "etiqueta": "Número de radicado", "grupo": "radicado", "valor": "2026-R-0001"}]}}
     */
    public function combinacion(Request $request, Documento $documento, VariablesPlantillaService $variables): JsonResponse
    {
        $radicado = $documento->radicado;
        $valores = $variables->valores($request->user(), $radicado);

        return $this->successResponse([
            'radicado' => $radicado ? [
                'id' => $radicado->id,
                'num_radicado' => $radicado->num_radicado,
                'asunto' => $radicado->asunto,
            ] : null,
            'variables' => array_map(
                fn ($variable) => $variable + ['valor' => $valores[$variable['clave']] ?? ''],
                $variables->catalogo()
            ),
        ], 'Datos de combinación obtenidos');
    }

    /** Descarga una plantilla cargada vigente. */
    public function descargarCargada(OfiArchivoPlantillaDocumento $cargada, PlantillaDocumentoService $archivos): BinaryFileResponse|JsonResponse
    {
        $ruta = $this->rutaCargada($cargada, $archivos);
        if (! $ruta) {
            return $this->notFoundResponse('La plantilla no está disponible');
        }

        return response()->download($ruta, $cargada->nombre_original);
    }

    /** Crea un documento en el editor a partir de una plantilla cargada de Word o texto. */
    public function usarCargada(
        Request $request,
        OfiArchivoPlantillaDocumento $cargada,
        PlantillaDocumentoService $archivos,
        DocumentoImportService $importador
    ): JsonResponse {
        $ruta = $this->rutaCargada($cargada, $archivos);
        if (! $ruta) {
            return $this->notFoundResponse('La plantilla no está disponible');
        }
        if (! in_array(strtolower((string) $cargada->extension), self::EXTENSIONES_EDITABLES, true)) {
            return $this->badRequestResponse('Esta plantilla no se puede abrir en el editor; descárguela.');
        }

        $archivo = new UploadedFile($ruta, $cargada->nombre_original, $cargada->mime_type, null, true);
        $documento = $importador->importar($archivo, $request->user()->id);

        return $this->createdResponse(['documento_id' => $documento->id], 'Documento creado desde la plantilla');
    }

    /** Ruta del archivo de una plantilla cargada si está vigente y existe; null si no. */
    private function rutaCargada(OfiArchivoPlantillaDocumento $cargada, PlantillaDocumentoService $archivos): ?string
    {
        if (! OfiArchivoPlantillaDocumento::vigente()->whereKey($cargada->id)->exists()) {
            return null;
        }
        $ruta = $archivos->descargarPlantilla($cargada);

        return is_file($ruta) ? $ruta : null;
    }

    /** Desde Mis Documentos solo se gestionan las plantillas personales propias. */
    private function soloPersonal(Request $request, OfiArchivoPlantillaGenerada $plantilla): void
    {
        abort_if($plantilla->institucional || $plantilla->user_id !== $request->user()->id, 404);
    }
}
