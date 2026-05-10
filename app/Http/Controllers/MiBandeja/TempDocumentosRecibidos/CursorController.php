<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Events\MiBandeja\TempReci\CursorActualizado;
use App\Http\Controllers\Controller;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de cursores colaborativos en documentos.
 *
 * Maneja la posición y selección de cursores de usuarios
 * en tiempo real para visualización compartida.
 */
class CursorController extends Controller
{
    /**
     * Obtiene los cursores activos del documento.
     *
     * Retorna usuarios con posición y selección actuales.
     * Excluye inactivos por más de 30 segundos.
     *
     * @param Documento $documento Documento
     * @return JsonResponse Lista de cursores
     *
     * @response 200 {
     *   "cursores": [
     *     {
     *       "user_id": 1,
     *       "nombre_usuario": "Juan Perez",
     *       "color": "#E53935",
     *       "posicion": 15,
     *       "seleccion_inicio": "10",
     *       "seleccion_fin": "15"
     *     }
     *   ]
     * }
     */
    public function obtenerCursores(Documento $documento): JsonResponse
    {
        $cursores = $documento->cursores()
            ->where('ultima_actividad', '>', now()->subSeconds(30))
            ->get()
            ->map(fn($cursor) => $cursor->toArray());

        return response()->json(['cursores' => $cursores]);
    }

    /**
     * Actualiza la posición del cursor del usuario.
     *
     * Guarda posición y selección del cursor.
     * Emite evento WebSocket para sincronización tiempo real.
     *
     * @param Request $request Solicitud
     * @param Documento $documento Documento
     * @return JsonResponse Cursor actualizado
     *
     * @bodyParam posicion integer required Posición del cursor. Example: 15
     * @bodyParam seleccion_inicio string Inicio de selección. Example: "10"
     * @bodyParam seleccion_fin string Fin de selección. Example: "15"
     *
     * @response 200 {
     *   "cursor": {
     *     "user_id": 1,
     *     "posicion": 15
     *   }
     * }
     *
     * @response 403 {
     *   "message": "No tienes permisos"
     * }
     *
     * @response 404 {
     *   "message": "Cursor no encontrado"
     * }
     */
    public function actualizarCursor(Request $request, Documento $documento): JsonResponse
    {
        $request->validate([
            'posicion' => 'required|integer|min:0',
            'seleccion_inicio' => 'nullable|string',
            'seleccion_fin' => 'nullable|string',
        ]);

        if (!$documento->puedeEditar($request->user())) {
            return response()->json(['message' => 'No tienes permisos'], 403);
        }

        $cursor = $documento->cursores()
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$cursor) {
            return response()->json(['message' => 'Cursor no encontrado'], 404);
        }

        $cursor->actualizarPosicion(
            $request->input('posicion'),
            $request->input('seleccion_inicio'),
            $request->input('seleccion_fin')
        );

        event(new CursorActualizado($documento->id, $cursor->toArray()));

        return response()->json(['cursor' => $cursor->toArray()]);
    }

    /**
     * Retorna el nombre del canal de broadcasting.
     *
     * @return string Nombre del canal
     */
    public function obtenerCanal(): string
    {
        return 'mi-bandeja.temp-reci.documentos';
    }
}
