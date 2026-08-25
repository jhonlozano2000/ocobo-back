<?php

namespace App\Http\Controllers\Transversal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Transversal\MisFirmasResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Transversal\FirmaEvento;
use Illuminate\Http\Request;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Http\JsonResponse;

/**
 * Controlador para consultar el historial de firmas electrónicas.
 * Normativa: Ley 527/1999 (Firma Electrónica), ISO 27001 A.8.15 (Logging).
 */
class FirmaEventosController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Retorna el historial de firmas electrónicas de un documento específico.
     *
     * @param  string  $tipo  reci|enviados|interno|pqrs
     * @param  int  $documentoId  ID del documento firmado
     */
    public function historial(string $tipo, int $documentoId): JsonResponse
    {
        $modelClass = $this->resolverModelo($tipo);

        if (! $modelClass) {
            return $this->errorResponse('Tipo de documento no válido. Use: reci, enviados, interno, pqrs', null, 422);
        }

        $eventos = FirmaEvento::where('documentable_type', $modelClass)
            ->where('documentable_id', $documentoId)
            ->with('user:id,nombres,apellidos,email')
            ->latest('fecha_firma')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'firmante' => $e->user ? trim("{$e->user->nombres} {$e->user->apellidos}") : 'Desconocido',
                'email_firmante' => $e->user?->email,
                'hash_original' => $e->hash_original,
                'hash_firmado' => $e->hash_firmado,
                'ip_address' => $e->ip_address,
                'fecha_firma' => $e->fecha_firma?->toIso8601String() ?? $e->created_at?->toIso8601String(),
                'integro' => $e->hash_original !== $e->hash_firmado,
            ]);

        return $this->successResponse([
            'tipo' => $tipo,
            'documento_id' => $documentoId,
            'total_firmas' => $eventos->count(),
            'firmas' => $eventos,
        ], 'Historial de firmas electrónicas');
    }

    public function misFirmas(Request $request): JsonResponse
    {
        $tipoMap = [
            'reci' => VentanillaRadicaReci::class,
            'enviados' => VentanillaRadicaEnviados::class,
            'interno' => VentanillaRadicaInterno::class,
            'pqrs' => VentanillaPqrs::class,
        ];

        $query = FirmaEvento::where('user_id', $request->user()->id)
            ->with('user');

        if ($tipo = $request->query('tipo')) {
            if (isset($tipoMap[$tipo])) {
                $query->where('documentable_type', $tipoMap[$tipo]);
            }
        }

        if ($desde = $request->query('desde')) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
                $query->whereDate('fecha_firma', '>=', $desde);
            }
        }

        if ($hasta = $request->query('hasta')) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
                $query->whereDate('fecha_firma', '<=', $hasta);
            }
        }

        $perPage = min((int) $request->query('per_page', 50), 100);
        $firmas = $query->latest('fecha_firma')->paginate($perPage);

        return $this->successResponse(MisFirmasResource::collection($firmas), 'Listado de firmas');
    }

    /**
     * Resuelve el nombre de clase Eloquent para el tipo de documento.
     */
    private function resolverModelo(string $tipo): ?string
    {
        return match ($tipo) {
            'reci' => VentanillaRadicaReci::class,
            'enviados' => VentanillaRadicaEnviados::class,
            'interno' => VentanillaRadicaInterno::class,
            'pqrs' => VentanillaPqrs::class,
            default => null,
        };
    }
}
