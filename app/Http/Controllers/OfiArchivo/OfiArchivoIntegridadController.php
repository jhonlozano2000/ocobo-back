<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoIntegridadIncidente;
use App\Models\OfiArchivo\OfiArchivoIntegridadVerificacion;
use App\Services\OfiArchivo\IntegridadArchivoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * B21 (diseño de expedientes §8): incidentes de la verificación de huellas de los folios y las
 * corridas que los detectaron. Las reglas están en IntegridadArchivoService.
 */
class OfiArchivoIntegridadController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private IntegridadArchivoService $integridad) {}

    /** Incidentes paginados, por estado y por número o nombre del expediente. */
    public function incidentes(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'estado' => 'nullable|in:abierto,resuelto',
            'tipo' => 'nullable|in:alterado,faltante,sin_huella',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $pagina = OfiArchivoIntegridadIncidente::with([
            'documento:id,expediente_id,numero_folio,tipo_documental,nombre_original',
            'expediente:id,numero_expediente,nombre_expediente,dependencia_nom,estado',
            'revision:id,hash_esperado,hash_calculado',
            'resueltoPor:id,nombres,apellidos',
        ])
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['search'] ?? null, fn ($q, $search) => $q->whereHas('expediente', fn ($q) => $q
                ->where('numero_expediente', 'like', "%{$search}%")
                ->orWhere('nombre_expediente', 'like', "%{$search}%")))
            ->orderByDesc('detectado_at')->orderByDesc('id')
            ->paginate($filtros['per_page'] ?? 15);

        return $this->successResponse($pagina, 'Incidentes de integridad obtenidos');
    }

    public function resolver(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['resolucion' => 'required|string|min:10|max:1000'], [
            'resolucion.required' => 'Explique cómo se resolvió el incidente.',
            'resolucion.min' => 'La explicación debe tener al menos :min caracteres.',
        ]);

        $incidente = $this->integridad->resolver(OfiArchivoIntegridadIncidente::findOrFail($id), $datos['resolucion'], $request->user());

        return $this->successResponse($incidente->load('resueltoPor:id,nombres,apellidos'), 'Incidente resuelto');
    }

    /** Verifica ya todos los folios de un expediente que el usuario puede ver. */
    public function verificarExpediente(Request $request, int $id): JsonResponse
    {
        $expediente = OfiArchivoExpediente::visiblesPara($request->user())->find($id);
        if (! $expediente) {
            return $this->notFoundResponse('Expediente no encontrado');
        }

        $verificacion = $this->integridad->verificarExpediente($expediente, $request->user());

        return $this->successResponse($verificacion, $verificacion->incidentes
            ? "{$verificacion->incidentes} folio(s) con problemas de integridad"
            : 'Todos los folios están íntegros');
    }

    /** Corridas de verificación, las más recientes primero. */
    public function verificaciones(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'alcance' => 'nullable|in:'.implode(',', OfiArchivoIntegridadVerificacion::ALCANCES),
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $pagina = OfiArchivoIntegridadVerificacion::with(['expediente:id,numero_expediente', 'usuario:id,nombres,apellidos'])
            ->when($filtros['alcance'] ?? null, fn ($q, $alcance) => $q->where('alcance', $alcance))
            ->orderByDesc('iniciada_at')->orderByDesc('id')
            ->paginate($filtros['per_page'] ?? 15);

        return $this->successResponse($pagina, 'Verificaciones de integridad obtenidas');
    }
}
