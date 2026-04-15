<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Helpers\FileMetadataHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetadataController extends Controller
{
    public function show(int $archivoId, string $tipo = 'reci'): JsonResponse
    {
        $resultado = FileMetadataHelper::obtenerMetadataConInfo($archivoId, $tipo);

        if (!$resultado) {
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
        $niveles = \App\Models\Configuracion\FileClassificationLevel::where('estado', true)
            ->orderBy('nivel_sensibilidad')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Niveles de clasificación obtenidos',
            'data' => $niveles,
        ]);
    }
}
