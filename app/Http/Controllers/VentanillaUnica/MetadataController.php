<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Helpers\FileMetadataHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\FileClassificationLevel;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosMetadata;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoMetadata;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetadataController extends Controller
{
    use ApiResponseTrait;

    public function show(int $archivoId, string $tipo = 'reci'): JsonResponse
    {
        $resultado = FileMetadataHelper::obtenerMetadataConInfo($archivoId, $tipo);

        if (! $resultado) {
            return response()->json([
                'status' => false,
                'message' => 'Metadatos no encontrados',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Metadatos obtenidos correctamente',
            'data' => $resultado,
        ]);
    }

    public function historial(int $metadataId, string $tipo = 'reci'): JsonResponse
    {
        $historial = FileMetadataHelper::obtenerHistorial($metadataId, $tipo);

        return response()->json([
            'status' => true,
            'message' => 'Historial obtenido correctamente',
            'data' => $historial,
        ]);
    }

    public function exportar(Request $request, string $tipo = 'reci'): StreamedResponse
    {
        $filtros = $request->only(['radicado_id', 'nivel_clasificacion', 'fecha_desde', 'fecha_hasta']);

        return FileMetadataHelper::exportarMetadata($filtros, $tipo);
    }

    public function nivelClasificacionIndex(): JsonResponse
    {
        $niveles = FileClassificationLevel::where('estado', true)
            ->orderBy('nivel_sensibilidad')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Niveles de clasificación obtenidos',
            'data' => $niveles,
        ]);
    }

    /**
     * Guarda o actualiza los metadatos editables de un archivo.
     * Permite actualizar: descripcion, palabras_clave, nivel_clasificacion,
     * clasificacion_id, es_registro_vital, categoria_informacion.
     *
     * @param  int  $archivoId  ID del archivo (adjunto o digital)
     * @param  string  $tipo  reci|enviados|interno
     */
    public function store(Request $request, int $archivoId, string $tipo = 'reci'): JsonResponse
    {
        $request->validate([
            'descripcion' => 'nullable|string|max:1000',
            'palabras_clave' => 'nullable|array',
            'palabras_clave.*' => 'string|max:100',
            'nivel_clasificacion' => 'nullable|string|in:PUBLICO,INTERNO,CONFIDENCIAL,RESERVADO,SECRETO',
            'nivel_clasificacion_id' => 'nullable|integer|exists:file_classification_levels,id',
            'clasificacion_id' => 'nullable|integer|exists:clasificacion_documental_trd,id',
            'es_registro_vital' => 'nullable|boolean',
            'categoria_informacion' => 'nullable|string|max:100',
        ]);

        $modelo = $this->resolverModelo($tipo);

        if (! $modelo) {
            return $this->errorResponse('Tipo de metadata no válido. Use: reci, enviados, interno', null, 422);
        }

        $metadata = $modelo::where('archivo_id', $archivoId)->first();

        if (! $metadata) {
            return $this->errorResponse('Metadatos no encontrados para el archivo indicado', null, 404);
        }

        $campos = array_filter([
            'descripcion' => $request->descripcion,
            'palabras_clave' => $request->palabras_clave,
            'nivel_clasificacion' => $request->nivel_clasificacion,
            'nivel_clasificacion_id' => $request->nivel_clasificacion_id,
            'clasificacion_id' => $request->clasificacion_id,
            'es_registro_vital' => $request->es_registro_vital,
            'categoria_informacion' => $request->categoria_informacion,
        ], fn ($v) => ! is_null($v));

        if (! empty($campos)) {
            $metadata->update($campos);
        }

        return $this->successResponse($metadata->fresh(), 'Metadatos actualizados exitosamente');
    }

    /**
     * Sugiere clasificaciones TRD basadas en el asunto de un radicado.
     * Busca coincidencias parciales en la TRD activa de la dependencia.
     *
     * @param  Request  $request  asunto: texto a buscar, dependencia_id: opcional
     */
    public function sugerirClasificacion(Request $request): JsonResponse
    {
        $request->validate([
            'asunto' => 'required|string|max:500',
            'dependencia_id' => 'nullable|integer',
        ]);

        $asunto = $request->asunto;
        $dependenciaId = $request->dependencia_id;

        // Extraer palabras clave del asunto (min 4 chars, eliminar stopwords básicas)
        $stopwords = ['para', 'con', 'por', 'que', 'una', 'los', 'las', 'del', 'sus', 'más', 'este', 'esta', 'ante', 'sobre'];
        $palabras = collect(preg_split('/\s+/', strtolower($asunto)))
            ->filter(fn ($p) => strlen($p) >= 4 && ! in_array($p, $stopwords))
            ->unique()
            ->take(5)
            ->values();

        if ($palabras->isEmpty()) {
            return $this->successResponse([], 'No se encontraron palabras clave para la sugerencia');
        }

        // Buscar TiposDocumento en TRD activa que coincidan
        $query = ClasificacionDocumentalTRD::where('tipo', 'TipoDocumento')
            ->where('estado', true)
            ->where(function ($q) use ($palabras) {
                foreach ($palabras as $palabra) {
                    $q->orWhere('nom', 'like', "%{$palabra}%");
                }
            });

        if ($dependenciaId) {
            $query->where('dependencia_id', $dependenciaId);
        }

        $sugerencias = $query->with(['parent' => fn ($q) => $q->with('parent')])
            ->limit(5)
            ->get()
            ->map(fn ($trd) => [
                'id' => $trd->id,
                'cod' => $trd->cod,
                'nom' => $trd->nom,
                'tipo' => $trd->tipo,
                'serie' => $trd->parent?->parent?->nom ?? $trd->parent?->nom,
                'subserie' => $trd->parent?->nom,
                'dias_vencimiento' => $trd->dias_vencimiento,
                'ruta' => implode(' › ', array_filter([
                    $trd->parent?->parent?->nom,
                    $trd->parent?->nom,
                    $trd->nom,
                ])),
            ]);

        return $this->successResponse($sugerencias, "Se encontraron {$sugerencias->count()} sugerencias");
    }

    /**
     * Resuelve el modelo de metadata correcto según el tipo.
     */
    private function resolverModelo(string $tipo): ?string
    {
        return match ($tipo) {
            'reci' => VentanillaRadicaReciMetadata::class,
            'enviados' => VentanillaRadicaEnviadosMetadata::class,
            'interno' => VentanillaRadicaInternoMetadata::class,
            default => null,
        };
    }
}
