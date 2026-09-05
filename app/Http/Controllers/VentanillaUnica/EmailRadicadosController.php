<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Helpers\ArchivoHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\RadicarEmailRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Configuracion\ConfigLista;
use App\Services\VentanillaUnica\EmailRadicacionService;
use App\Services\VentanillaUnica\ImapEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para la bandeja de correo electrónico.
 * Lee directamente de Gmail IMAP sin pasar por la BD.
 */
class EmailRadicadosController extends Controller
{
    use ApiResponseTrait;

    private const PERM = 'Radicar -> Cores. Recibida -> ';

    public function __construct(
        private ImapEmailService $imapService,
        private EmailRadicacionService $emailRadicacionService
    ) {
        $this->middleware('can:'.self::PERM.'Listar')->only(['index', 'estadisticas']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['show', 'rotulo']);
        $this->middleware('can:'.self::PERM.'Crear')->only(['radicar', 'responder']);
    }

    /**
     * Lista emails del inbox de Gmail con paginación.
     * Lee directamente de IMAP.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            $perPage = max(1, min((int) $request->input('per_page', 15), 100));
            $search = $request->filled('search') ? $request->search : null;

            $result = $this->imapService->fetchPaginatedInbox($page, $perPage, $search);

            // Envolver en formato compatible con el frontend (Laravel paginator format)
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $result['emails'],
                $result['pagination']['total'],
                $result['pagination']['perPage'],
                $result['pagination']['currentPage'],
                ['path' => '/api/ventanilla/email-radicados']
            );

            return $this->successResponse(
                $paginator,
                'Correos obtenidos exitosamente'
            );
        } catch (\Exception $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'incompleta') || str_contains($message, 'host, usuario')) {
                return $this->errorResponse(
                    'Configuración IMAP incompleta',
                    'Debe configurar el host IMAP, puerto y credenciales.',
                    422
                );
            }

            if (str_contains($message, 'connection') || str_contains($message, 'connect')) {
                return $this->errorResponse(
                    'No se pudo conectar al servidor de correo',
                    'Verifique que el host y puerto IMAP sean correctos.',
                    502
                );
            }

            if (str_contains($message, 'credentials') || str_contains($message, 'authenticate')) {
                return $this->errorResponse(
                    'Credenciales IMAP incorrectas',
                    'El usuario o contraseña son incorrectos.',
                    401
                );
            }

            return $this->errorResponse(
                'Error al obtener correos',
                $message ?: 'Error desconocido.',
                500
            );
        }
    }

    /**
     * Obtiene detalles de un email específico por su UID de IMAP.
     */
    public function show(string $uid): JsonResponse
    {
        try {
            $email = $this->imapService->getMessageDetails($uid);

            if (! $email) {
                return $this->errorResponse('Correo no encontrado', null, 404);
            }

            return $this->successResponse($email, 'Correo obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al obtener el correo',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Crea un radicado (recibido o enviado) a partir de un correo electrónico.
     * Recibe los datos del email en el request (no de la BD).
     */
    public function radicar(string $uid, RadicarEmailRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $tipoRadicado = $validatedData['tipo_radicado'] ?? 'recibido';

            // Los datos del email vienen en el request desde el frontend
            $emailData = [
                'uid' => $uid,
                'asunto' => $validatedData['email_asunto'] ?? '',
                'remitente_email' => $validatedData['email_remitente_email'] ?? '',
                'remitente_nombre' => $validatedData['email_remitente_nombre'] ?? '',
                'fecha_correo' => $validatedData['email_fecha_correo'] ?? null,
                'body_text' => $validatedData['email_body_text'] ?? '',
                'body_html' => $validatedData['email_body_html'] ?? '',
                'tiene_adjuntos' => $validatedData['email_tiene_adjuntos'] ?? false,
                'adjuntos_info' => $validatedData['email_adjuntos_info'] ?? [],
            ];

            if ($tipoRadicado === 'enviado') {
                $resultado = $this->emailRadicacionService->radicarEnviadoFromEmailData($emailData, $validatedData);
            } else {
                $resultado = $this->emailRadicacionService->radicarFromEmailData($emailData, $validatedData);
            }

            $diskName = $tipoRadicado === 'enviado' ? 'radicados_enviados' : 'radicados_recibidos';
            if (! empty($resultado['pdf_path'])) {
                $resultado['file_url'] = ArchivoHelper::obtenerUrl($resultado['pdf_path'], $diskName);
            }

            $mensaje = $tipoRadicado === 'enviado'
                ? 'Radicado enviado creado exitosamente desde el correo'
                : 'Radicado recibido creado exitosamente desde el correo';

            return $this->successResponse($resultado, $mensaje, 201);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'medio de recepción')) {
                return $this->errorResponse(
                    'Error de configuración',
                    'No existe el medio de recepción "Correo Electrónico". Contacte al administrador.',
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
     * Responde a un correo radicado adjuntando el rótulo.
     */
    public function responder(string $uid, Request $request): JsonResponse
    {
        try {
            $request->validate(['mensaje' => 'required|string']);

            $email = $this->imapService->getMessageDetails($uid);

            if (! $email) {
                return $this->errorResponse('Correo no encontrado en el servidor IMAP', null, 404);
            }

            $resultado = $this->emailRadicacionService->responderConRadicadoDirecto(
                $email,
                $request->mensaje
            );

            if (! $resultado) {
                return $this->errorResponse(
                    'No se pudo enviar la respuesta',
                    'Verifique la configuración SMTP.',
                    500
                );
            }

            return $this->successResponse($resultado, 'Respuesta enviada exitosamente');
        } catch (\Exception $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'SMTP') || str_contains($message, 'mail')) {
                return $this->errorResponse(
                    'Error al enviar el correo',
                    'No se pudo conectar al servidor SMTP.',
                    502
                );
            }

            return $this->errorResponse(
                'Error al responder el correo',
                $message ?: 'Error desconocido.',
                500
            );
        }
    }

    /**
     * Descarga el rótulo de un radicado.
     */
    public function rotulo(string $numRadicado)
    {
        try {
            $rotuloPath = $this->emailRadicacionService->descargarRotuloPorNum($numRadicado);

            if (! $rotuloPath || ! file_exists($rotuloPath)) {
                return $this->errorResponse(
                    'No se encontró el archivo de rótulo.',
                    null,
                    404
                );
            }

            return response()->file($rotuloPath, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="rotulo.png"',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al generar el rótulo',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Estadísticas de correos (placeholder — IMAP no tiene estadísticas persistentes).
     */
    public function estadisticas(): JsonResponse
    {
        return $this->successResponse([
            'pendientes' => 0,
            'radicados' => 0,
            'respondidos' => 0,
            'errores' => 0,
            'total' => 0,
        ], 'Estadísticas no disponibles con conexión directa a IMAP');
    }

    /**
     * Obtiene las etiquetas disponibles desde config_listas.
     */
    public function etiquetasDisponibles(): JsonResponse
    {
        try {
            $lista = ConfigLista::where('cod', 'LabEmail')->first();

            if (! $lista) {
                return $this->successResponse([], 'No hay etiquetas configuradas');
            }

            $etiquetas = $lista->detalles()
                ->where('estado', 1)
                ->get()
                ->map(fn ($d) => [
                    'codigo' => $d->codigo,
                    'nombre' => $d->nombre,
                ]);

            return $this->successResponse($etiquetas, 'Etiquetas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las etiquetas', $e->getMessage(), 500);
        }
    }
}
