<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Workflows\WorkflowInstancia;
use App\Services\OfiArchivo\PendientesArchivoService;
use App\Services\Workflows\ArchivoFlujoEnExpedienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bandeja "Pendientes de archivar" (diseño de expedientes §3.4 y §6.1).
 */
class OfiArchivoPendientesController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request, PendientesArchivoService $service): JsonResponse
    {
        return $this->successResponse($service->para($request->user()), 'Pendientes de archivar');
    }

    /**
     * M19-30: vuelve a incorporar los archivos de un flujo completado, típicamente después de
     * llevar su radicado a un expediente abierto. Bloquea la instancia para no foliar dos veces.
     */
    public function reintentar(Request $request, int $instanciaId, ArchivoFlujoEnExpedienteService $archivo): JsonResponse
    {
        $user = $request->user();

        $resultado = DB::transaction(function () use ($instanciaId, $user, $archivo) {
            $instancia = WorkflowInstancia::where('estado', 'completada')
                ->whereIn('radicado_id', VentanillaRadicaReci::conPermisoJerarquico($user)->select('id'))
                ->lockForUpdate()
                ->find($instanciaId);

            return $instancia ? $archivo->archivar($instancia) : false;
        });

        if ($resultado === false) {
            return $this->errorResponse('Instancia no encontrada', null, 404);
        }

        if (($resultado['estado'] ?? null) !== 'incorporado') {
            return $this->errorResponse($resultado['mensaje'] ?? 'No se pudieron archivar los documentos del flujo.', $resultado, 422);
        }

        return $this->successResponse($resultado, 'Archivos del flujo incorporados al expediente '.$resultado['numero_expediente']);
    }
}
