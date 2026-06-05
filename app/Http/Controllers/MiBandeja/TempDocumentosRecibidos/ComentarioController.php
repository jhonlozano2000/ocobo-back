<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Helpers\OutputSanitizer;
use App\Models\MiBandeja\TempDocumentosRecibidos\Comentario;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de comentarios en documentos colaborativos.
 *
 * Maneja la creación, edición, eliminación y resolución
 * de comentarios con soporte para respuestas anidadas.
 */
class ComentarioController extends Controller
{
    /**
     * Lista los comentarios de un documento.
     *
     * Retorna comentarios principales (sin parent_id) con sus respuestas.
     * Ordenados por fecha descendente.
     *
     * @param Documento $documento Documento
     * @return JsonResponse Lista de comentarios
     *
     * @response 200 {
     *   "comentarios": [
     *     {
     *       "id": 1,
     *       "contenido": "Revisar este párrafo",
     *       "usuario": {"id": 1, "name": "Juan Perez"},
     *       "respuestas": []
     *     }
     *   ]
     * }
     */
    public function index(Documento $documento): JsonResponse
    {
        $comentarios = $documento->comentarios()
            ->whereNull('parent_id')
            ->with(['usuario:id,name', 'respuestas.usuario:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['comentarios' => $comentarios]);
    }

    /**
     * Crea un comentario en el documento.
     *
     * Puede ser comentario principal o respuesta a otro.
     * Incluye opcionalmente selección de texto para contexto.
     *
     * @param Request $request Solicitud
     * @param Documento $documento Documento
     * @return JsonResponse Comentario creado
     *
     * @bodyParam contenido string required Texto del comentario (1-2000 caracteres). Example: "Revisar este párrafo"
     * @bodyParam seleccion_texto object Selección de texto relacionada. Example: {"from": 0, "to": 10, "text": "texto"}
     * @bodyParam parent_id integer ID del comentario padre (respuesta). Example: 1
     *
     * @response 201 {
     *   "message": "Comentario creado correctamente",
     *   "comentario": {"id": 1, "contenido": "Revisar este párrafo"}
     * }
     *
     * @response 400 {
     *   "message": "El comentario padre no pertenece a este documento"
     * }
     *
     * @response 403 {
     *   "message": "No tienes acceso"
     * }
     *
     * @response 422 {
     *   "errors": {"contenido": ["El contenido es obligatorio"]}
     * }
     */
    public function store(Request $request, Documento $documento): JsonResponse
    {
        $request->validate([
            'contenido' => 'required|string|min:1|max:2000',
            'seleccion_texto' => 'nullable|array',
            'parent_id' => 'nullable|exists:mi_bandeja_temp_reci_comentarios,id',
        ]);

        if (!$documento->tieneAcceso($request->user())) {
            return response()->json(['message' => 'No tienes acceso'], 403);
        }

        if ($request->input('parent_id')) {
            $padre = Comentario::findOrFail($request->input('parent_id'));
            if ($padre->documento_id !== $documento->id) {
                return response()->json(['message' => 'El comentario padre no pertenece a este documento'], 400);
            }
        }

        $comentario = $documento->comentarios()->create([
            'user_id' => $request->user()->id,
            'contenido' => OutputSanitizer::sanitize($request->input('contenido')),
            'seleccion_texto' => $request->input('seleccion_texto')
                ? OutputSanitizer::sanitize($request->input('seleccion_texto')['text'] ?? '')
                : null,
            'parent_id' => $request->input('parent_id'),
        ]);

        return response()->json([
            'message' => 'Comentario creado correctamente',
            'comentario' => $comentario->load('usuario:id,name'),
        ], 201);
    }

    /**
     * Actualiza un comentario existente.
     *
     * Solo el autor puede editar su comentario.
     *
     * @param Request $request Solicitud
     * @param Comentario $comentario Comentario
     * @return JsonResponse Comentario actualizado
     *
     * @bodyParam contenido string required Nuevo texto. Example: "Contenido modificado"
     *
     * @response 200 {
     *   "message": "Comentario actualizado",
     *   "comentario": {"id": 1, "contenido": "Contenido modificado"}
     * }
     *
     * @response 403 {
     *   "message": "Solo el autor puede editar el comentario"
     * }
     */
    public function update(Request $request, Comentario $comentario): JsonResponse
    {
        if ($comentario->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Solo el autor puede editar el comentario'], 403);
        }

        $request->validate([
            'contenido' => 'required|string|min:1|max:2000',
        ]);

        $comentario->update(['contenido' => OutputSanitizer::sanitize($request->input('contenido'))]);

        return response()->json([
            'message' => 'Comentario actualizado',
            'comentario' => $comentario,
        ]);
    }

    /**
     * Elimina un comentario.
     *
     * Solo el autor puede eliminar su comentario.
     * Las respuestas también se eliminan en cascada.
     *
     * @param Request $request Solicitud
     * @param Comentario $comentario Comentario
     * @return JsonResponse Confirmación
     *
     * @response 200 {
     *   "message": "Comentario eliminado"
     * }
     *
     * @response 403 {
     *   "message": "Solo el autor puede eliminar el comentario"
     * }
     */
    public function destroy(Request $request, Comentario $comentario): JsonResponse
    {
        if ($comentario->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Solo el autor puede eliminar el comentario'], 403);
        }

        $comentario->delete();

        return response()->json(['message' => 'Comentario eliminado']);
    }

    /**
     * Resuelve un comentario y sus respuestas.
     *
     * Marca como resuelto un comentario y todas sus respuestas.
     * Requiere permiso 'gestionar-comentarios'.
     *
     * @param Request $request Solicitud
     * @param Comentario $comentario Comentario
     * @return JsonResponse Confirmación
     *
     * @response 200 {
     *   "message": "Comentario resuelto"
     * }
     *
     * @response 403 {
     *   "message": "No tienes permiso para resolver comentarios"
     * }
     */
    public function resolver(Request $request, Comentario $comentario): JsonResponse
    {
        if (!$request->user()->can('gestionar-comentarios')) {
            return response()->json(['message' => 'No tienes permiso para resolver comentarios'], 403);
        }

        $comentario->resolver();

        return response()->json(['message' => 'Comentario resuelto']);
    }

    public function desresolver(Request $request, Comentario $comentario): JsonResponse
    {
        if (!$request->user()->can('gestionar-comentarios')) {
            return response()->json(['message' => 'No tienes permiso para gestionar comentarios'], 403);
        }

        $comentario->desmarcarResuelto();

        return response()->json(['message' => 'Comentario marcado como pendiente']);
    }
}
