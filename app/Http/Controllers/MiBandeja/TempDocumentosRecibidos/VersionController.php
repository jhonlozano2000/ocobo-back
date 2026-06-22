<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\MiBandeja\TempDocumentosRecibidos\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VersionController extends Controller
{
    public function index(Request $request, Documento $documento): JsonResponse
    {
        if (! $documento->tieneAcceso($request->user())) {
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
        if (! $documento->tieneAcceso($request->user())) {
            return response()->json(['message' => 'No tienes acceso'], 403);
        }

        $version = $documento->versiones()->where('id', $versionId)
            ->with('usuario:id,name,email')
            ->first();

        if (! $version) {
            return response()->json(['message' => 'Versión no encontrada'], 404);
        }

        return response()->json($version);
    }

    public function restaurar(Request $request, Documento $documento, int $versionId): JsonResponse
    {
        if (! $documento->puedeEditar($request->user())) {
            return response()->json(['message' => 'No tienes permisos'], 403);
        }

        $version = $documento->versiones()->where('id', $versionId)->first();

        if (! $version) {
            return response()->json(['message' => 'Versión no encontrada'], 404);
        }

        return DB::transaction(function () use ($version, $documento, $request) {
            $contenido = $version->restaurar();
            $documento->contenido->actualizarContenido($contenido, $request->user());

            Version::crearVersion($documento, $contenido, $request->user(), "Restaurado desde versión {$version->numero_version}");

            return response()->json([
                'contenido' => $contenido,
                'version' => $version,
            ]);
        });
    }

    public function compararVersiones(Request $request, Documento $documento, int $versionIdA, int $versionIdB): JsonResponse
    {
        if (! $documento->tieneAcceso($request->user())) {
            return response()->json(['message' => 'No tienes acceso'], 403);
        }

        $versionA = $documento->versiones()->where('id', $versionIdA)->first();
        $versionB = $documento->versiones()->where('id', $versionIdB)->first();

        if (! $versionA || ! $versionB) {
            return response()->json(['message' => 'Una o ambas versiones no existen'], 404);
        }

        $diff = $this->calcularDiff($versionA->contenido_yjs ?? [], $versionB->contenido_yjs ?? []);

        return response()->json([
            'version_a' => [
                'id' => $versionA->id,
                'numero_version' => $versionA->numero_version,
                'descripcion' => $versionA->descripcion,
                'created_at' => $versionA->created_at->toISOString(),
                'usuario' => $versionA->usuario->name ?? 'Usuario',
            ],
            'version_b' => [
                'id' => $versionB->id,
                'numero_version' => $versionB->numero_version,
                'descripcion' => $versionB->descripcion,
                'created_at' => $versionB->created_at->toISOString(),
                'usuario' => $versionB->usuario->name ?? 'Usuario',
            ],
            'diff' => $diff,
        ]);
    }

    private function calcularDiff(array $contenidoA, array $contenidoB): array
    {
        $textoA = $this->extraerTextoPlano($contenidoA);
        $textoB = $this->extraerTextoPlano($contenidoB);

        if ($textoA === $textoB) {
            return [
                'son_iguales' => true,
                'cambios' => [],
                'resumen' => [
                    'lineas_agregadas' => 0,
                    'lineas_eliminadas' => 0,
                    'lineas_modificadas' => 0,
                ],
            ];
        }

        $lineasA = explode("\n", $textoA);
        $lineasB = explode("\n", $textoB);

        $diff = [];
        $agregadas = 0;
        $eliminadas = 0;
        $modificadas = 0;

        $maxLineas = max(count($lineasA), count($lineasB));

        for ($i = 0; $i < $maxLineas; $i++) {
            $lineaA = $lineasA[$i] ?? null;
            $lineaB = $lineasB[$i] ?? null;

            if ($lineaA === null && $lineaB !== null) {
                $diff[] = [
                    'tipo' => 'agregado',
                    'linea' => $i + 1,
                    'contenido' => $lineaB,
                ];
                $agregadas++;
            } elseif ($lineaA !== null && $lineaB === null) {
                $diff[] = [
                    'tipo' => 'eliminado',
                    'linea' => $i + 1,
                    'contenido' => $lineaA,
                ];
                $eliminadas++;
            } elseif ($lineaA !== $lineaB) {
                $diff[] = [
                    'tipo' => 'modificado',
                    'linea' => $i + 1,
                    'anterior' => $lineaA,
                    'nuevo' => $lineaB,
                ];
                $modificadas++;
            }
        }

        return [
            'son_iguales' => false,
            'cambios' => $diff,
            'resumen' => [
                'lineas_agregadas' => $agregadas,
                'lineas_eliminadas' => $eliminadas,
                'lineas_modificadas' => $modificadas,
                'total_cambios' => $agregadas + $eliminadas + $modificadas,
            ],
        ];
    }

    private function extraerTextoPlano(array $contenido): string
    {
        $texto = '';

        foreach ($contenido as $bloque) {
            if (! isset($bloque['type'])) {
                continue;
            }

            match ($bloque['type']) {
                'doc' => $texto .= $this->extraerTextoPlano($bloque['content'] ?? []),
                'paragraph' => $texto .= $this->extraerTextoInline($bloque['content'] ?? [])."\n",
                'heading' => $texto .= strtoupper($this->extraerTextoInline($bloque['content'] ?? []))."\n\n",
                'bulletList', 'orderedList' => $texto .= $this->extraerTextoLista($bloque)."\n",
                'blockquote' => $texto .= '> '.$this->extraerTextoInline($bloque['content'] ?? [])."\n\n",
                'codeBlock' => $texto .= $this->extraerTextoInline($bloque['content'] ?? [])."\n\n",
                'horizontalRule' => $texto .= "---\n\n",
                'table' => $texto .= $this->extraerTextoTabla($bloque)."\n",
                default => null,
            };
        }

        return trim($texto);
    }

    private function extraerTextoInline(array $content): string
    {
        $texto = '';
        foreach ($content as $inline) {
            if (isset($inline['text'])) {
                $texto .= $inline['text'];
            }
        }

        return $texto;
    }

    private function extraerTextoLista(array $lista): string
    {
        $texto = '';
        $prefijo = $lista['type'] === 'bulletList' ? '• ' : '';
        $i = 1;

        foreach ($lista['content'] ?? [] as $item) {
            if ($prefijo) {
                $texto .= $prefijo.$this->extraerTextoInline($item['content'][0]['content'] ?? [])."\n";
            } else {
                $texto .= $i.'. '.$this->extraerTextoInline($item['content'][0]['content'] ?? [])."\n";
                $i++;
            }
        }

        return $texto;
    }

    private function extraerTextoTabla(array $tabla): string
    {
        $texto = '';
        foreach ($tabla['content'] ?? [] as $fila) {
            $celdas = [];
            foreach ($fila['content'] ?? [] as $celda) {
                $celdas[] = $this->extraerTextoInline($celda['content'] ?? []);
            }
            $texto .= implode(' | ', $celdas)."\n";
        }

        return $texto;
    }
}
