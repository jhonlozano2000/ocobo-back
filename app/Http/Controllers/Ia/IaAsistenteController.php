<?php

namespace App\Http\Controllers\Ia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ia\BuscarPrecedentesIaRequest;
use App\Http\Requests\Ia\ProyectarBorradorIaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Jobs\Ia\ProyectarBorradorIaJob;
use App\Models\Ia\IaBorradorAuditoria;
use App\Services\Ia\IaAsistenteService;
use App\Services\Ia\IaDocumentoIntegracionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Controlador de Asistente de IA para el SGDEA OCOBO.
 *
 * Expone endpoints seguros para consulta de precedentes y proyección de respuestas oficiales
 * asistidas por RAG y el modelo LLM local, garantizando privacidad (Ley 1581) y control ABAC.
 */
class IaAsistenteController extends Controller
{
    use ApiResponseTrait;

    protected IaAsistenteService $iaService;

    public function __construct(IaAsistenteService $iaService)
    {
        $this->iaService = $iaService;
    }

    /**
     * Consulta el estado de operatividad de la IA local y la cola de procesamiento.
     */
    public function status(Request $request): JsonResponse
    {
        $estado = $this->iaService->obtenerEstado();

        if (! ($estado['online'] ?? false)) {
            return $this->warningResponse(
                $estado['error'] ?? 'El servicio de IA no se encuentra disponible en este momento.',
                ['online' => false]
            );
        }

        return $this->successResponse(
            $estado['data'],
            'Servicio de IA local operativo.'
        );
    }

    /**
     * Busca y recupera precedentes documentales institucionales con filtro ABAC por jerarquía.
     */
    public function buscarPrecedentes(BuscarPrecedentesIaRequest $request): JsonResponse
    {
        $user = $request->user();
        $consulta = (string) $request->input('consulta');
        $topK = (int) $request->input('top_k', 5);
        $flagHc = (bool) $request->input('flag_hc', false);
        $flagTutela = (bool) $request->input('flag_tutela', false);

        $resultado = $this->iaService->buscarPrecedentes(
            $consulta,
            $topK,
            $user,
            $flagHc,
            $flagTutela
        );

        if (! ($resultado['success'] ?? false)) {
            return $this->warningResponse(
                $resultado['error'] ?? 'No se pudieron recuperar los precedentes documentales.',
                $resultado
            );
        }

        return $this->successResponse(
            $resultado,
            'Precedentes documentales recuperados exitosamente.'
        );
    }

    /**
     * Proyecta un borrador formal de respuesta institucional a partir de un radicado.
     */
    public function proyectarBorrador(ProyectarBorradorIaRequest $request): JsonResponse
    {
        $user = $request->user();
        $datos = $request->validated();

        $resultado = $this->iaService->proyectarBorrador($datos, $user);

        // Si el motor de IA se abstuvo por falta de precedentes suficientes
        if (($resultado['estado'] ?? '') === 'ABSTENCION_SIN_PRECEDENTE') {
            return $this->warningResponse(
                'El asistente no encontró precedentes institucionales suficientes para responder automáticamente. Se recomienda proyección manual.',
                $resultado
            );
        }

        // Si ocurrió un error en la llamada al motor
        if (! ($resultado['success'] ?? false)) {
            return $this->warningResponse(
                $resultado['error'] ?? 'No fue posible generar el borrador de respuesta.',
                $resultado
            );
        }

        return $this->successResponse(
            $resultado,
            'Borrador institucional generado exitosamente a partir de precedentes verificados.'
        );
    }

    /**
     * Encola la proyección de borrador en segundo plano y notifica por Laravel Reverb (F2-03).
     */
    public function proyectarBorradorAsync(ProyectarBorradorIaRequest $request): JsonResponse
    {
        $user = $request->user();
        $datos = $request->validated();
        $jobId = (string) Str::uuid();

        ProyectarBorradorIaJob::dispatch(
            $user->id,
            $jobId,
            $datos,
            $request->ip(),
            $request->userAgent()
        );

        return $this->successResponse(
            [
                'job_id' => $jobId,
                'canal' => 'ia-asistente.'.$user->id,
                'evento' => 'borrador.generado',
                'estado' => 'PROCESANDO_EN_SEGUNDO_PLANO',
            ],
            'Proyección de borrador encolada exitosamente. Recibirá la notificación en tiempo real.'
        );
    }

    /**
     * Consulta el historial de auditoría de inferencias de IA (ISO 27001).
     */
    public function historialAuditoria(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = IaBorradorAuditoria::query()->recientes();

        // Si no tiene permiso de ver todos los registros de IA, filtrar solo los propios
        if (! $user->can('Asistente IA -> Ver Todos') && ! $user->can('Radicar -> Ver Todos')) {
            $query->paraUsuario($user->id);
        }

        if ($request->filled('estado')) {
            $query->estado((string) $request->input('estado'));
        }

        if ($request->filled('id_radicado')) {
            $query->where('id_radicado', 'like', '%'.$request->input('id_radicado').'%');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginados = $query->paginate($perPage);

        return $this->successResponse(
            $paginados,
            'Historial de auditoría de IA recuperado exitosamente.'
        );
    }

    /**
     * Crea un documento colaborativo en Mi Bandeja a partir del borrador de la IA (F2-04).
     */
    public function crearDocumento(
        CrearDocumentoDesdeBorradorIaRequest $request,
        IaDocumentoIntegracionService $integracionService
    ): JsonResponse {
        $user = $request->user();
        $datos = $request->validated();

        $documento = $integracionService->crearDocumentoColaborativo($datos, $user);

        return $this->createdResponse(
            [
                'id' => $documento->id,
                'titulo' => $documento->titulo,
                'estado' => $documento->estado,
                'radica_reci_id' => $documento->radica_reci_id,
                'created_at' => $documento->created_at->toISOString(),
            ],
            'Documento colaborativo creado exitosamente a partir del borrador institucional.'
        );
    }

    /**
     * Exporta directamente el borrador de IA a un archivo .docx de Word con variables institucionales.
     */
    public function exportarDocx(
        CrearDocumentoDesdeBorradorIaRequest $request,
        IaDocumentoIntegracionService $integracionService
    ) {
        $user = $request->user();
        $datos = $request->validated();

        try {
            $resultado = $integracionService->exportarDocxDesdeBorrador($datos, $user);

            return response()->download(
                $resultado['ruta'],
                $resultado['nombre'],
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ]
            )->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse(
                'No fue posible exportar el documento Word a partir del borrador.',
                $e->getMessage()
            );
        }
    }
}
