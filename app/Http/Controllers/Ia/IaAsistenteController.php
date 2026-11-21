<?php

namespace App\Http\Controllers\Ia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ia\BuscarPrecedentesIaRequest;
use App\Http\Requests\Ia\ProyectarBorradorIaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\Ia\IaAsistenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
