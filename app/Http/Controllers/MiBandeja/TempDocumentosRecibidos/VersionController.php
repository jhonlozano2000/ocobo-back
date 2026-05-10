<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\MiBandeja\TempDocumentosRecibidos\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersionController extends Controller
{
    public function index(Request $request, Documento $documento): JsonResponse
    {
        if (!$documento->tieneAcceso($request->user())) {
            return response()->json(['message' => 'No tienes acceso'], 403);
        }

        $versiones = Version::where('documento_id', $documento->id)
            ->with('usuario:id,name,email')
            ->orderBy('numero_version', 'desc')
            ->paginate(20);

        return response()->json($versiones);
    }

    public function show(Request $request, Documento $documento, int $versionId): JsonResponse
    {
        if (!$documento->tieneAcceso($request->user())) {
            return response()->json(['message' => 'No tienes acceso'], 403);
        }

        $version = Version::where('documento_id', $documento->id)
            ->where('id', $versionId)
            ->with('usuario:id,name,email')
            ->first();

        if (!$version) {
            return response()->json(['message' => 'Versión no encontrada'], 404);
        }

        return response()->json($version);
    }

    public function restaurar(Request $request, Documento $documento, int $versionId): JsonResponse
    {
        if (!$documento->puedeEditar($request->user())) {
            return response()->json(['message' => 'No tienes permisos'], 403);
        }

        $version = Version::where('documento_id', $documento->id)
            ->where('id', $versionId)
            ->first();

        if (!$version) {
            return response()->json(['message' => 'Versión no encontrada'], 404);
        }

        $contenido = $version->restaurar();
        $documento->contenido->actualizarContenido($contenido, $request->user());

        Version::crearVersion($documento, $contenido, $request->user(), "Restaurado desde versión {$version->numero_version}");

        return response()->json([
            'contenido' => $contenido,
            'version' => $version,
        ]);
    }
}