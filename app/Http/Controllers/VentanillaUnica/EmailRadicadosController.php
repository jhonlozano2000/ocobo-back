<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Helpers\ArchivoHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\RadicarEmailRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\VentanillaEmailRadicado;
use App\Services\VentanillaUnica\EmailRadicacionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailRadicadosController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> Cores. Recibida -> ';

    public function __construct(
        private EmailRadicacionService $emailRadicacionService
    ) {
        $this->middleware('can:'.self::PERM.'Listar')->only(['index', 'estadisticas']);
        $this->middleware('can:'.self::PERM.'Sincronizar')->only(['sincronizar']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['show', 'rotulo']);
        $this->middleware('can:'.self::PERM.'Crear')->only(['radicar', 'responder']);
        $this->middleware('can:'.self::PERM.'Eliminar')->only(['destroy']);
    }

    /**
     * Lista los correos radicados con paginación y filtros.
     *
     *
     * @queryParam estado string Filtrar por estado del correo. Example: "pendiente"
     * @queryParam search string Buscar por asunto o remitente. Example: "factura"
     * @queryParam fecha_desde string Filtrar desde fecha (YYYY-MM-DD). Example: "2024-01-01"
     * @queryParam fecha_hasta string Filtrar hasta fecha (YYYY-MM-DD). Example: "2024-12-31"
     * @queryParam per_page integer Número de elementos por página. Example: 15
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = VentanillaEmailRadicado::query()
                ->with(['radicado']);

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('asunto', 'like', "%{$search}%")
                        ->orWhere('remitente', 'like', "%{$search}%")
                        ->orWhere('correo_remitente', 'like', "%{$search}%");
                });
            }

            if ($request->filled('fecha_desde')) {
                $query->where('fecha_correo', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->where('fecha_correo', '<=', $request->fecha_hasta);
            }

            $perPage = max(1, min((int) $request->input('per_page', 15), 100));
            $correoRadicados = $query->orderByDesc('created_at')->paginate($perPage);

            return $this->successResponse(
                $correoRadicados,
                'Listado de correos radicados obtenido exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al obtener el listado de correos radicados',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Sincroniza correos IMAP y genera radicados automáticamente.
     */
    public function sincronizar(): JsonResponse
    {
        try {
            $resultado = $this->emailRadicacionService->sincronizarCorreos();

            return $this->successResponse(
                $resultado,
                'Sincronización de correos ejecutada exitosamente'
            );
        } catch (\Exception $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'incompleta') || str_contains($message, 'host, usuario')) {
                return $this->errorResponse(
                    'Configuración IMAP incompleta',
                    'Debe configurar el host IMAP, puerto y credenciales en Configuración → Otras Configuraciones → pestaña Correo.',
                    422
                );
            }

            if (str_contains($message, 'connection') || str_contains($message, 'connect')) {
                return $this->errorResponse(
                    'No se pudo conectar al servidor de correo',
                    'Verifique que el host y puerto IMAP sean correctos y que el servidor esté disponible.',
                    502
                );
            }

            if (str_contains($message, 'credentials') || str_contains($message, 'authenticate') || str_contains($message, 'password')) {
                return $this->errorResponse(
                    'Credenciales IMAP incorrectas',
                    'El usuario o contraseña del correo electrónico son incorrectos. Verifique en Configuración → Otras Configuraciones.',
                    401
                );
            }

            return $this->errorResponse(
                'Error al sincronizar correos',
                $message ?: 'Error desconocido. Consulte el registro de errores del servidor.',
                500
            );
        }
    }

    /**
     * Muestra los detalles de un correo radicado.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $correoRadicado = VentanillaEmailRadicado::with([
                'radicado.clasificacionDocumental',
                'radicado.tercero',
                'radicado.servidorArchivos',
            ])->findOrFail($id);

            return $this->successResponse(
                $correoRadicado,
                'Correo radicado obtenido exitosamente'
            );
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Correo radicado no encontrado', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al obtener el correo radicado',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Crea un radicado (recibido o enviado) a partir de un correo electrónico.
     *
     *
     * @bodyParam tipo_radicado string required Tipo de radicado: "recibido" o "enviado". Example: "recibido"
     * @bodyParam clasifica_documen_id integer required ID de la clasificación documental. Example: 1
     * @bodyParam tercero_id integer ID del tercero. Example: 5
     * @bodyParam medio_recep_id integer ID del medio de recepción (para recibido). Example: 1
     * @bodyParam medio_enviado_id integer ID del medio de envío (para enviado). Example: 1
     * @bodyParam asunto string Asunto del radicado. Example: "Solicitud de información"
     * @bodyParam num_folios integer Número de folios. Example: 5
     * @bodyParam num_anexos integer Número de anexos. Example: 2
     * @bodyParam descrip_anexos string Descripción de anexos. Example: "Documentos adjuntos"
     * @bodyParam notas string Notas adicionales. Example: "Referencia al correo"
     */
    public function radicar(int $id, RadicarEmailRequest $request): JsonResponse
    {
        \Log::debug('EmailRadicadosController: radicar INICIO', ['id' => $id]);

        try {
            $correoRadicado = VentanillaEmailRadicado::findOrFail($id);
            \Log::debug('EmailRadicadosController: Correo encontrado', [
                'id' => $correoRadicado->id,
                'estado' => $correoRadicado->estado,
                'radicado_id' => $correoRadicado->radicado_id,
            ]);

            if ($correoRadicado->radicado_id) {
                \Log::warning('EmailRadicadosController: Correo ya tiene radicado', ['radicado_id' => $correoRadicado->radicado_id]);

                return $this->errorResponse(
                    'Este correo ya tiene un radicado asociado',
                    null,
                    422
                );
            }

            $validatedData = $request->validated();
            \Log::debug('EmailRadicadosController: Datos validados', $validatedData);

            $tipoRadicado = $validatedData['tipo_radicado'];
            \Log::debug('EmailRadicadosController: Tipo radicado', ['tipo' => $tipoRadicado]);

            if ($tipoRadicado === 'enviado') {
                \Log::debug('EmailRadicadosController: Llamando radicarEnviadoFromEmail');
                $resultado = $this->emailRadicacionService->radicarEnviadoFromEmail(
                    $correoRadicado->id,
                    $validatedData
                );
            } else {
                \Log::debug('EmailRadicadosController: Llamando radicarFromEmail');
                $resultado = $this->emailRadicacionService->radicarFromEmail(
                    $correoRadicado->id,
                    $validatedData
                );
            }

            \Log::debug('EmailRadicadosController: Resultado:', $resultado);

            // Agregar file_url para que el frontend pueda mostrar/descargar el PDF
            $diskName = $tipoRadicado === 'enviado' ? 'radicados_enviados' : 'radicados_recibidos';
            if (! empty($resultado['pdf_path'])) {
                $resultado['file_url'] = ArchivoHelper::obtenerUrl($resultado['pdf_path'], $diskName);
            }

            $mensaje = $tipoRadicado === 'enviado'
                ? 'Radicado enviado creado exitosamente desde el correo'
                : 'Radicado recibido creado exitosamente desde el correo';

            return $this->successResponse(
                $resultado,
                $mensaje,
                201
            );
        } catch (ModelNotFoundException) {
            \Log::error('EmailRadicadosController: Correo no encontrado', ['id' => $id]);

            return $this->errorResponse('Correo radicado no encontrado', null, 404);
        } catch (\Exception $e) {
            \Log::error('EmailRadicadosController: Exception:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $message = $e->getMessage();

            if (str_contains($message, 'medio de recepción')) {
                return $this->errorResponse(
                    'Error de configuración',
                    'No existe el medio de recepción "Correo Electrónico" en el sistema. Contacte al administrador.',
                    500
                );
            }

            return $this->errorResponse(
                'Error al radicar el correo',
                $message ?: 'Error desconocido al crear el radicado.',
                500
            );
        }
    }

    /**
     * Descarga el rótulo del correo radicado como imagen PNG.
     *
     * @return StreamedResponse|JsonResponse
     */
    public function rotulo(int $id)
    {
        try {
            $correoRadicado = VentanillaEmailRadicado::with('radicado')->findOrFail($id);

            if (! $correoRadicado->radicado_id) {
                return $this->errorResponse(
                    'El correo no tiene un radicado asociado para generar rótulo',
                    null,
                    422
                );
            }

            $rotuloPath = $this->emailRadicacionService->descargarRotulo($correoRadicado->id);

            if (! $rotuloPath || ! file_exists($rotuloPath)) {
                return $this->errorResponse(
                    'No se encontró el archivo de rótulo. Genere el radicado primero.',
                    null,
                    404
                );
            }

            return response()->file($rotuloPath, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="rotulo.png"',
            ]);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Correo radicado no encontrado', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al generar el rótulo',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Responde a un correo radicado adjuntando el rótulo.
     *
     *
     * @bodyParam mensaje string Mensaje de la respuesta. Required. Example: "Adjunto radicado."
     */
    public function responder(int $id, Request $request): JsonResponse
    {
        try {
            $correoRadicado = VentanillaEmailRadicado::findOrFail($id);

            $request->validate([
                'mensaje' => 'required|string',
            ]);

            $resultado = $this->emailRadicacionService->responderConRadicado(
                $correoRadicado->id,
                $request->mensaje
            );

            if (! $resultado) {
                return $this->errorResponse(
                    'No se pudo enviar la respuesta',
                    'Verifique la configuración de correo saliente (SMTP) en Configuración → Otras Configuraciones.',
                    500
                );
            }

            return $this->successResponse(
                $resultado,
                'Respuesta enviada exitosamente'
            );
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Correo radicado no encontrado', null, 404);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'SMTP') || str_contains($message, 'mail')) {
                return $this->errorResponse(
                    'Error al enviar el correo',
                    'No se pudo conectar al servidor de correo saliente. Verifique la configuración SMTP.',
                    502
                );
            }

            return $this->errorResponse(
                'Error al responder el correo',
                $message ?: 'Error desconocido al enviar la respuesta.',
                500
            );
        }
    }

    /**
     * Obtiene estadísticas de correos radicados por estado.
     */
    public function estadisticas(): JsonResponse
    {
        try {
            $estadisticas = VentanillaEmailRadicado::selectRaw('estado, COUNT(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado');

            $total = $estadisticas->sum();

            return $this->successResponse([
                'pendientes' => $estadisticas->get('pendiente', 0),
                'radicados' => $estadisticas->get('radicado', 0),
                'respondidos' => $estadisticas->get('respondido', 0),
                'errores' => $estadisticas->get('error', 0),
                'total' => $total,
            ], 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al obtener las estadísticas',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Elimina lógicamente el registro de seguimiento de un correo radicado.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $correoRadicado = VentanillaEmailRadicado::findOrFail($id);
            $correoRadicado->delete();

            return $this->successResponse(null, 'Correo radicado eliminado exitosamente');
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Correo radicado no encontrado', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al eliminar el correo radicado',
                $e->getMessage(),
                500
            );
        }
    }
}
