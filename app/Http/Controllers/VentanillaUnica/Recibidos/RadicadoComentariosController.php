<?php

namespace App\Http\Controllers\VentanillaUnica\Recibidos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciComentario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RadicadoComentariosController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> Cores. Recibida -> ';
    private const PERM_COMENTARIOS = 'Radicar -> Cores. Recibida -> Comentar';

    public function __construct()
    {
        $this->middleware('can:' . self::PERM . 'Mostrar')->only(['index', 'show']);
        $this->middleware('can:' . self::PERM_COMENTARIOS . 'Crear')->only(['store']);
        $this->middleware('can:' . self::PERM_COMENTARIOS . 'Editar')->only(['update', 'resolver']);
        $this->middleware('can:' . self::PERM_COMENTARIOS . 'Eliminar')->only(['destroy']);
    }

    public function index(int $radicaReciId): JsonResponse
    {
        $radicado = VentanillaRadicaReci::find($radicaReciId);
        if (!$radicado) {
            return $this->apiResponseError('Radicado no encontrado', 404);
        }

        $comentarios = $radicado->comentarios()
            ->with(['usuario', 'respuestas.usuario', 'usuarioResolucion'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->apiResponseSuccess(
            $comentarios->map(fn($c) => $c->getInfo()),
            'Comentarios obtenidos exitosamente'
        );
    }

    public function show(int $id): JsonResponse
    {
        $comentario = VentanillaRadicaReciComentario::with(['usuario', 'respuestas.usuario'])
            ->find($id);

        if (!$comentario) {
            return $this->apiResponseError('Comentario no encontrado', 404);
        }

        return $this->apiResponseSuccess($comentario->getInfo(), 'Comentario obtenido exitosamente');
    }

    public function store(Request $request, int $radicaReciId): JsonResponse
    {
        $request->validate([
            'contenido' => 'required|string|max:5000',
            'parent_id' => 'nullable|integer|exists:ventanilla_radica_reci_comentarios,id',
            'etiquetas' => 'nullable|string|max:1000',
            'es_nota_interna' => 'nullable|boolean',
        ]);

        $radicado = VentanillaRadicaReci::find($radicaReciId);
        if (!$radicado) {
            return $this->apiResponseError('Radicado no encontrado', 404);
        }

        if ($request->parent_id) {
            $parent = VentanillaRadicaReciComentario::find($request->parent_id);
            if (!$parent || $parent->radica_reci_id !== $radicaReciId) {
                return $this->apiResponseError('Comentario padre no válido', 400);
            }
        }

        try {
            $comentario = DB::transaction(function () use ($request, $radicaReciId) {
                $etiquetas = null;
                if ($request->etiquetas) {
                    $etiquetas = array_filter(array_map('trim', explode(',', $request->etiquetas)));
                }

                return VentanillaRadicaReciComentario::create([
                    'radica_reci_id' => $radicaReciId,
                    'user_id' => Auth::id(),
                    'contenido' => $request->contenido,
                    'parent_id' => $request->parent_id,
                    'etiquetas' => $etiquetas,
                    'es_nota_interna' => $request->boolean('es_nota_interna', false),
                ]);
            });

            $comentario->load(['usuario', 'respuestas']);

            return $this->apiResponseSuccess(
                $comentario->getInfo(),
                'Comentario creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->apiResponseError('Error al crear comentario: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'contenido' => 'required|string|max:5000',
        ]);

        $comentario = VentanillaRadicaReciComentario::find($id);
        if (!$comentario) {
            return $this->apiResponseError('Comentario no encontrado', 404);
        }

        if ($comentario->user_id !== Auth::id()) {
            return $this->apiResponseError('Solo el autor puede editar el comentario', 403);
        }

        if ($comentario->resuelto) {
            return $this->apiResponseError('No se puede editar un comentario resuelto', 400);
        }

        try {
            $comentario->update(['contenido' => $request->contenido]);
            $comentario->load(['usuario', 'respuestas']);

            return $this->apiResponseSuccess($comentario->getInfo(), 'Comentario actualizado');
        } catch (\Exception $e) {
            return $this->apiResponseError('Error al actualizar: ' . $e->getMessage(), 500);
        }
    }

    public function resolver(int $id): JsonResponse
    {
        $comentario = VentanillaRadicaReciComentario::find($id);
        if (!$comentario) {
            return $this->apiResponseError('Comentario no encontrado', 404);
        }

        if ($comentario->resuelto) {
            return $this->apiResponseError('El comentario ya está resuelto', 400);
        }

        try {
            $comentario->resolver(Auth::id());
            $comentario->load(['usuario', 'usuarioResolucion']);

            return $this->apiResponseSuccess($comentario->getInfo(), 'Comentario resuelto');
        } catch (\Exception $e) {
            return $this->apiResponseError('Error al resolver: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $comentario = VentanillaRadicaReciComentario::find($id);
        if (!$comentario) {
            return $this->apiResponseError('Comentario no encontrado', 404);
        }

        if ($comentario->user_id !== Auth::id()) {
            return $this->apiResponseError('Solo el autor puede eliminar el comentario', 403);
        }

        if ($comentario->respuestas()->count() > 0) {
            return $this->apiResponseError('No se puede eliminar un comentario con respuestas', 400);
        }

        try {
            $comentario->delete();
            return $this->apiResponseSuccess(null, 'Comentario eliminado');
        } catch (\Exception $e) {
            return $this->apiResponseError('Error al eliminar: ' . $e->getMessage(), 500);
        }
    }
}
