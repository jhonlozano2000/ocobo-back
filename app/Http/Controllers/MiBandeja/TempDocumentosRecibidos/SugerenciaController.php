<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Helpers\OutputSanitizer;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\MiBandeja\TempDocumentosRecibidos\Sugerencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SugerenciaController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request, Documento $documento): JsonResponse
    {
        if (! $documento->tieneAcceso($request->user())) {
            return $this->forbiddenResponse('No tienes acceso');
        }

        $estado = $request->query('estado');
        $tipo = $request->query('tipo');

        $query = $documento->sugerencias()->with(['usuario:id,nombres,apellidos', 'resueltoPor:id,nombres,apellidos']);

        if ($estado) {
            $query->where('estado', $estado);
        }

        if ($tipo) {
            $query->where('tipo', $tipo);
        }

        $sugerencias = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->successResponse($sugerencias, 'Sugerencias obtenidas exitosamente');
    }

    public function store(Request $request, Documento $documento): JsonResponse
    {
        if (! $documento->puedeEditar($request->user())) {
            return $this->forbiddenResponse('No tienes permisos para editar');
        }

        $validated = $request->validate([
            'tipo' => 'required|in:insercion,eliminacion,reemplazo,formato',
            'texto_original' => 'nullable|string|max:5000',
            'texto_sugerido' => 'nullable|string|max:5000',
            'posicion' => 'required|array',
            'posicion.from' => 'required|integer|min:0',
            'posicion.to' => 'required|integer|min:0',
            'justificacion' => 'nullable|string|max:2000',
            'parent_id' => 'nullable|exists:mi_bandeja_temp_reci_sugerencias,id',
        ]);

        if ($request->input('parent_id')) {
            $padre = Sugerencia::findOrFail($request->input('parent_id'));
            if ($padre->documento_id !== $documento->id) {
                return $this->badRequestResponse('La sugerencia padre no pertenece a este documento');
            }
        }

        $sugerencia = $documento->sugerencias()->create([
            'user_id' => $request->user()->id,
            'tipo' => $validated['tipo'],
            'texto_original' => $validated['texto_original'] ? OutputSanitizer::sanitize($validated['texto_original']) : null,
            'texto_sugerido' => $validated['texto_sugerido'] ? OutputSanitizer::sanitize($validated['texto_sugerido']) : null,
            'posicion' => $validated['posicion'],
            'justificacion' => $validated['justificacion'] ? OutputSanitizer::sanitize($validated['justificacion']) : null,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        return $this->createdResponse([
            'sugerencia' => $sugerencia->load('usuario:id,nombres,apellidos'),
        ], 'Sugerencia creada correctamente');
    }

    public function aceptar(Request $request, Documento $documento, Sugerencia $sugerencia): JsonResponse
    {
        if (! $documento->puedeEditar($request->user())) {
            return $this->forbiddenResponse('No tienes permisos');
        }

        if ($sugerencia->documento_id !== $documento->id) {
            return $this->badRequestResponse('La sugerencia no pertenece a este documento');
        }

        if ($sugerencia->estado !== 'pendiente') {
            return $this->badRequestResponse('La sugerencia ya fue resuelta');
        }

        return DB::transaction(function () use ($request, $documento, $sugerencia) {
            $sugerencia->aceptar($request->user());

            if ($documento->contenido) {
                $contenidoActual = $documento->contenido->contenido_yjs ?? [];
                $contenidoActualizado = $sugerencia->aplicarAlContenido($contenidoActual);

                if ($contenidoActualizado !== $contenidoActual) {
                    $documento->contenido->actualizarContenido($contenidoActualizado, $request->user());
                }
            }

            return $this->successResponse([
                'sugerencia' => $sugerencia->load('resueltoPor:id,nombres,apellidos'),
            ], 'Sugerencia aceptada y aplicada al documento');
        });
    }

    public function rechazar(Request $request, Documento $documento, Sugerencia $sugerencia): JsonResponse
    {
        if (! $documento->puedeEditar($request->user())) {
            return $this->forbiddenResponse('No tienes permisos');
        }

        if ($sugerencia->documento_id !== $documento->id) {
            return $this->badRequestResponse('La sugerencia no pertenece a este documento');
        }

        if ($sugerencia->estado !== 'pendiente') {
            return $this->badRequestResponse('La sugerencia ya fue resuelta');
        }

        $sugerencia->rechazar($request->user());

        return $this->successResponse([
            'sugerencia' => $sugerencia->load('resueltoPor:id,nombres,apellidos'),
        ], 'Sugerencia rechazada');
    }

    public function destroy(Request $request, Documento $documento, Sugerencia $sugerencia): JsonResponse
    {
        if ($sugerencia->user_id !== $request->user()->id && $documento->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('No tienes permisos para eliminar esta sugerencia');
        }

        if ($sugerencia->documento_id !== $documento->id) {
            return $this->badRequestResponse('La sugerencia no pertenece a este documento');
        }

        $sugerencia->delete();

        return $this->successResponse(null, 'Sugerencia eliminada');
    }

    public function estadisticas(Request $request, Documento $documento): JsonResponse
    {
        if (! $documento->tieneAcceso($request->user())) {
            return $this->forbiddenResponse('No tienes acceso');
        }

        $estadisticas = [
            'total' => $documento->sugerencias()->count(),
            'pendientes' => $documento->sugerencias()->where('estado', 'pendiente')->count(),
            'aceptadas' => $documento->sugerencias()->where('estado', 'aceptada')->count(),
            'rechazadas' => $documento->sugerencias()->where('estado', 'rechazada')->count(),
            'por_tipo' => $documento->sugerencias()
                ->selectRaw('tipo, COUNT(*) as count')
                ->groupBy('tipo')
                ->pluck('count', 'tipo'),
        ];

        return $this->successResponse($estadisticas, 'Estadísticas de sugerencias obtenidas');
    }
}
