<?php

namespace App\Http\Controllers\VentanillaUnica\Pqrs;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsComentario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Controlador VentanillaPqrsComentariosController - Comentarios threaded PQRS
 *
 * Gestiona comentarios con hilos (respuestas anidadas), notas internas,
 * selección de texto, y marcado de resuelto. Permisos granulares por acción:
 * crear, editar, eliminar, resolver.
 *
 * Permisos (patrón módulo Recibidos):
 * - Mostrar: 'Radicar -> PQRSF -> Mostrar' (index, show)
 * - Comentar: 'Radicar -> PQRSF -> Comentar' (store, update, resolver, destroy)
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */
class VentanillaPqrsComentariosController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> PQRSF -> ';
    private const PERM_COMENTARIOS = 'Radicar -> PQRSF -> Comentar';

    public function __construct()
    {
        $this->middleware('can:' . self::PERM . 'Mostrar')->only(['index', 'show']);
        $this->middleware('can:' . self::PERM_COMENTARIOS)->only(['store', 'update', 'resolver', 'destroy']);
    }

    /**
     * Lista todos los comentarios de un PQRS con estructura de árbol.
     * Usa getInfo() del modelo que incluye respuestas recursivas.
     *
     * @param int $pqrsId ID del PQRS
     * @return JsonResponse Array de comentarios con respuestas anidadas
     */
    public function index(int $pqrsId): JsonResponse
    {
        $pqrs = VentanillaPqrs::find($pqrsId);
        if (! $pqrs) {
            return $this->errorResponse('PQRS no encontrado', null, 404);
        }

        $comentarios = $pqrs->comentarios()
            ->with(['usuario', 'respuestas.usuario'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(
            $comentarios->map(fn($c) => $c->getInfo()),
            'Comentarios obtenidos exitosamente'
        );
    }

    /**
     * Obtiene un comentario específico con sus respuestas.
     *
     * @param int $id ID del comentario
     * @return JsonResponse Comentario con estructura getInfo()
     */
    public function show(int $id): JsonResponse
    {
        $comentario = VentanillaPqrsComentario::with(['usuario', 'respuestas.usuario'])
            ->find($id);

        if (! $comentario) {
            return $this->errorResponse('Comentario no encontrado', null, 404);
        }

        return $this->successResponse($comentario->getInfo(), 'Comentario obtenido exitosamente');
    }

    /**
     * Crea un nuevo comentario (raíz o respuesta a otro).
     *
     * @param Request $request
     * @param int $pqrsId ID del PQRS
     * @return JsonResponse Comentario creado con getInfo() (201)
     *
     * Body request:
     *   - contenido (string, requerido, max: 5000): Texto del comentario
     *   - parent_id (int, opcional): ID del comentario padre (para respuestas)
     *   - seleccion_texto (array, opcional): {inicio, fin, texto} para referenciar texto
     *   - es_nota_interna (bool, opcional, default: false): Nota visible solo para internos
     */
    public function store(Request $request, int $pqrsId): JsonResponse
    {
        $request->validate([
            'contenido' => 'required|string|max:5000',
            'parent_id' => 'nullable|integer|exists:ventanilla_pqrs_comentarios,id',
            'seleccion_texto' => 'nullable|array',
            'es_nota_interna' => 'nullable|boolean',
        ]);

        $pqrs = VentanillaPqrs::find($pqrsId);
        if (! $pqrs) {
            return $this->errorResponse('PQRS no encontrado', null, 404);
        }

        if ($request->parent_id) {
            $parent = VentanillaPqrsComentario::find($request->parent_id);
            if (! $parent || $parent->pqrs_id !== $pqrsId) {
                return $this->errorResponse('Comentario padre no válido', null, 400);
            }
        }

        try {
            $comentario = DB::transaction(function () use ($request, $pqrsId) {
                return VentanillaPqrsComentario::create([
                    'pqrs_id' => $pqrsId,
                    'user_id' => Auth::id(),
                    'contenido' => $request->contenido,
                    'parent_id' => $request->parent_id,
                    'seleccion_texto' => $request->seleccion_texto,
                    'es_nota_interna' => $request->boolean('es_nota_interna', false),
                ]);
            });

            $comentario->load(['usuario', 'respuestas']);

            return $this->successResponse(
                $comentario->getInfo(),
                'Comentario creado exitosamente',
                201
            );
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al crear comentario', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza el contenido de un comentario propio.
     * Solo el autor puede editar, y solo si no está resuelto.
     *
     * @param Request $request
     * @param int $id ID del comentario
     * @return JsonResponse Comentario actualizado
     *
     * Body request:
     *   - contenido (string, requerido, max: 5000): Nuevo contenido
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'contenido' => 'required|string|max:5000',
        ]);

        $comentario = VentanillaPqrsComentario::find($id);
        if (! $comentario) {
            return $this->errorResponse('Comentario no encontrado', null, 404);
        }

        if ($comentario->user_id !== Auth::id()) {
            return $this->errorResponse('Solo el autor puede editar el comentario', null, 403);
        }

        if ($comentario->resuelto) {
            return $this->errorResponse('No se puede editar un comentario resuelto', null, 400);
        }

        try {
            $comentario->update(['contenido' => $request->contenido]);
            $comentario->load(['usuario', 'respuestas']);

            return $this->successResponse($comentario->getInfo(), 'Comentario actualizado');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al actualizar', $e->getMessage(), 500);
        }
    }

    /**
     * Marca un comentario como resuelto.
     * Requiere permiso 'Comentar'.
     *
     * @param int $id ID del comentario
     * @return JsonResponse Comentario con resuelto=true, resuelto_por, fecha_resolucion
     */
    public function resolver(int $id): JsonResponse
    {
        $comentario = VentanillaPqrsComentario::find($id);
        if (! $comentario) {
            return $this->errorResponse('Comentario no encontrado', null, 404);
        }

        if ($comentario->resuelto) {
            return $this->errorResponse('El comentario ya está resuelto', null, 400);
        }

        try {
            $comentario->resolver(Auth::id());
            $comentario->load(['usuario']);

            return $this->successResponse($comentario->getInfo(), 'Comentario resuelto');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al resolver', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un comentario propio.
     * Solo el autor puede eliminar, y solo si no tiene respuestas.
     *
     * @param int $id ID del comentario
     * @return JsonResponse Confirmación de eliminación
     */
    public function destroy(int $id): JsonResponse
    {
        $comentario = VentanillaPqrsComentario::find($id);
        if (! $comentario) {
            return $this->errorResponse('Comentario no encontrado', null, 404);
        }

        if ($comentario->user_id !== Auth::id()) {
            return $this->errorResponse('Solo el autor puede eliminar el comentario', null, 403);
        }

        if ($comentario->respuestas()->count() > 0) {
            return $this->errorResponse('No se puede eliminar un comentario con respuestas', null, 400);
        }

        try {
            $comentario->delete();

            return $this->successResponse(null, 'Comentario eliminado');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al eliminar', $e->getMessage(), 500);
        }
    }
}