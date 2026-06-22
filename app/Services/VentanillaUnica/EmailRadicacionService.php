<?php

namespace App\Services\VentanillaUnica;

use App\Helpers\MailConfigHelper;
use App\Mail\RespuestaRadicadoMail;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\VentanillaUnica\VentanillaEmailRadicado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio que orquesta la radicación de correos electrónicos.
 * Coordina la sincronización IMAP, creación de radicados (recibidos/enviados) y generación de rótulos.
 */
class EmailRadicacionService
{
    protected ImapEmailService $imapService;

    protected RotuloPngService $rotuloService;

    protected PdfService $pdfService;

    protected RadicacionReciService $reciService;

    protected RadicacionEnviadosService $enviadosService;

    public function __construct(
        ?ImapEmailService $imapService = null,
        ?RotuloPngService $rotuloService = null,
        ?PdfService $pdfService = null,
        ?RadicacionReciService $reciService = null,
        ?RadicacionEnviadosService $enviadosService = null
    ) {
        $this->imapService = $imapService ?? new ImapEmailService;
        $this->rotuloService = $rotuloService ?? new RotuloPngService;
        $this->pdfService = $pdfService ?? new PdfService;
        $this->reciService = $reciService ?? new RadicacionReciService;
        $this->enviadosService = $enviadosService ?? new RadicacionEnviadosService;
    }

    /**
     * Sincroniza correos desde el buzón IMAP hacia la tabla de seguimiento.
     *
     * @return array Resumen de la sincronización [total, nuevos, duplicados, errores]
     */
    public function sincronizarCorreos(): array
    {
        $resultado = [
            'total' => 0,
            'nuevos' => 0,
            'duplicados' => 0,
            'errores' => 0,
        ];

        try {
            $correos = $this->imapService->fetchInboxMessages(7);
            $resultado['total'] = count($correos);

            foreach ($correos as $correoData) {
                try {
                    $existe = VentanillaEmailRadicado::where('imap_uid', $correoData['uid'])->exists();

                    if ($existe) {
                        $resultado['duplicados']++;

                        continue;
                    }

                    VentanillaEmailRadicado::create([
                        'imap_uid' => $correoData['uid'],
                        'imap_folder' => 'INBOX',
                        'asunto' => $correoData['asunto'],
                        'remitente_email' => $correoData['remitente_email'],
                        'remitente_nombre' => $correoData['remitente_nombre'],
                        'fecha_correo' => $correoData['fecha_correo'],
                        'body_text' => $correoData['body_text'],
                        'body_html' => $correoData['body_html'],
                        'tiene_adjuntos' => $correoData['tiene_adjuntos'],
                        'adjuntos_info' => $correoData['adjuntos_info'],
                        'estado' => 'pendiente',
                        'sincronizado_en' => now(),
                    ]);

                    $resultado['nuevos']++;
                } catch (\Exception $e) {
                    Log::error('EmailRadicacionService: Error al guardar correo individual', [
                        'uid' => $correoData['uid'] ?? 'desconocido',
                        'error' => $e->getMessage(),
                    ]);
                    $resultado['errores']++;
                }
            }
        } catch (\Exception $e) {
            Log::error('EmailRadicacionService: Error en sincronización de correos', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $resultado;
    }

    /**
     * Crea un radicado RECIBIDO a partir de un correo electrónico.
     *
     * @param  int  $emailId  ID del registro en ventanilla_email_radicados
     * @param  array  $data  Datos del formulario (clasifica_documen_id, tercero_id, medio_recep_id, etc.)
     * @return array ['radicado' => VentanillaRadicaReci, 'rotulo_path' => string, 'pdf_path' => string|null]
     */
    public function radicarFromEmail(int $emailId, array $data = []): array
    {
        \Log::debug('EmailRadicacionService: radicarFromEmail INICIO', ['emailId' => $emailId, 'data' => $data]);

        return DB::transaction(function () use ($emailId, $data) {
            $email = VentanillaEmailRadicado::findOrFail($emailId);
            \Log::debug('EmailRadicacionService: Email encontrado', ['id' => $email->id, 'estado' => $email->estado]);

            if ($email->estado === 'radicado') {
                \Log::warning('EmailRadicacionService: Email ya radicado');
                throw new \Exception('Este correo ya ha sido radicado anteriormente.');
            }

            // Hash SHA-256 ISO 27001 A.10.1.2
            $hashSha256 = $this->calcularHashEmail($email);
            \Log::debug('EmailRadicacionService: Hash calculado', ['hash' => substr($hashSha256, 0, 16).'...']);

            // Número de radicado
            $numRadicado = $this->obtenerSiguienteNumeroRadicado();
            \Log::debug('EmailRadicacionService: Número de radicado', ['num_radicado' => $numRadicado]);

            // Medio de recepción
            $medioRecepId = $data['medio_recep_id'] ?? null;
            if (! $medioRecepId) {
                $medioRecepcion = ConfigListaDetalle::where('nombre', 'Correo Electrónico')->first();
                $medioRecepId = $medioRecepcion?->id;
            }

            \Log::debug('EmailRadicacionService: Medio recepción', ['medio_recep_id' => $medioRecepId]);

            if (! $medioRecepId) {
                \Log::error('EmailRadicacionService: Medio de recepción no encontrado');
                throw new \Exception('No se encontró el medio de recepción "Correo Electrónico" en la configuración.');
            }

            // Crear radicado recibido
            $radicadoData = [
                'num_radicado' => $numRadicado,
                'clasifica_documen_id' => $data['clasifica_documen_id'] ?? null,
                'tercero_id' => $data['tercero_id'] ?? null,
                'usuario_crea' => auth()->id(),
                'medio_recep_id' => $medioRecepId,
                'asunto' => $data['asunto'] ?? $email->asunto,
                'nom_origi' => $data['nom_razo_soci'] ?? $email->remitente_nombre,
                'fec_docu' => $email->fecha_correo?->format('Y-m-d'),
                'num_folios' => $data['num_folios'] ?? 0,
                'num_anexos' => $data['num_anexos'] ?? count($email->adjuntos_info ?? []),
                'descrip_anexos' => $data['descrip_anexos'] ?? ($email->tiene_adjuntos
                    ? collect($email->adjuntos_info ?? [])->pluck('filename')->implode(', ')
                    : null),
                'estado_trabajo' => 'RECIBIDO',
                'cod_verifica' => strtoupper(substr(uniqid('OCOBO-'), 0, 10)),
                'hash_sha256' => $hashSha256,
            ];
            \Log::debug('EmailRadicacionService: Creando radicado con datos:', $radicadoData);

            $radicado = $this->reciService->create($radicadoData);
            \Log::debug('EmailRadicacionService: Radicado creado:', ['id' => $radicado->id, 'num_radicado' => $radicado->num_radicado]);

            // Guardar adjuntos
            if ($email->tiene_adjuntos && ! empty($email->adjuntos_info)) {
                $this->guardarAdjuntosEmail($email, $radicado);
            }

            // Convertir email a PDF y guardar como archivo digital
            $pdfPath = $this->convertEmailToPdf($email, $radicado, 'recibido');

            // Actualizar nom_origi con el nombre del PDF generado
            if ($pdfPath) {
                $radicado->update(['nom_origi' => basename($pdfPath)]);
            }

            // Actualizar tracking
            $email->update([
                'radicado_id' => $radicado->id,
                'estado' => 'radicado',
                'radicado_en' => now(),
            ]);

            // Generar rótulo PNG
            $rotuloPath = $this->rotuloService->generarRotulo([
                'num_radicado' => $numRadicado,
                'fecha_radicado' => $radicado->created_at->format('Y-m-d H:i:s'),
                'remitente_nombre' => $email->remitente_nombre,
                'remitente_email' => $email->remitente_email,
                'asunto' => $email->asunto,
                'clasificacion' => $radicado->clasificacionDocumental?->nom ?? '',
                'codigo_verificacion' => $radicado->cod_verifica,
                'hash_sha256' => $hashSha256,
            ]);

            // Respuesta automática al remitente original
            $replyResult = false;
            try {
                $replyResult = $this->responderConRadicado(
                    $emailId,
                    'Su correo ha sido radicado exitosamente con el número ' . $numRadicado . '. Adjunto rótulo del radicado.'
                );
            } catch (\Exception $e) {
                \Log::warning('EmailRadicacionService: Error al enviar respuesta automática (recibido)', [
                    'email_id' => $emailId,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'radicado' => $radicado->load(['clasificacionDocumental', 'tercero', 'medioRecepcion']),
                'rotulo_path' => $rotuloPath,
                'pdf_path' => $pdfPath,
                'respuesta_enviada' => $replyResult,
            ];
        });
    }

    /**
     * Crea un radicado ENVIADO a partir de un correo electrónico.
     *
     * @param  int  $emailId  ID del registro en ventanilla_email_radicados
     * @param  array  $data  Datos del formulario (clasifica_documen_id, tercero_id, medio_enviado_id, etc.)
     * @return array ['radicado' => VentanillaRadicaEnviados, 'rotulo_path' => string, 'pdf_path' => string|null]
     */
    public function radicarEnviadoFromEmail(int $emailId, array $data = []): array
    {
        return DB::transaction(function () use ($emailId, $data) {
            $email = VentanillaEmailRadicado::findOrFail($emailId);

            if ($email->estado === 'radicado') {
                throw new \Exception('Este correo ya ha sido radicado anteriormente.');
            }

            // Hash SHA-256 ISO 27001 A.10.1.2
            $hashSha256 = $this->calcularHashEmail($email);

            // Número de radicado enviado
            $numRadicado = $this->obtenerSiguienteNumeroRadicadoEnviado();

            // Medio de envío
            $medioEnvioId = $data['medio_enviado_id'] ?? null;
            if (! $medioEnvioId) {
                $medioEnvio = ConfigListaDetalle::where('nombre', 'Correo Electrónico')->first();
                $medioEnvioId = $medioEnvio?->id;
            }

            // Tipo de respuesta
            $tipoRespuestaId = $data['tipo_respuesta_id'] ?? null;

            // Crear radicado enviado
            $radicadoData = [
                'num_radicado' => $numRadicado,
                'clasifica_documen_id' => $data['clasifica_documen_id'] ?? null,
                'tercero_id' => $data['tercero_id'] ?? null,
                'usuario_crea' => auth()->id(),
                'medio_enviado_id' => $medioEnvioId,
                'tipo_respuesta_id' => $tipoRespuestaId,
                'asunto' => $data['asunto'] ?? $email->asunto,
                'nom_origi' => $data['nom_razo_soci'] ?? $email->remitente_nombre,
                'fec_docu' => $email->fecha_correo?->format('Y-m-d'),
                'num_folios' => $data['num_folios'] ?? 0,
                'num_anexos' => $data['num_anexos'] ?? count($email->adjuntos_info ?? []),
                'descrip_anexos' => $data['descrip_anexos'] ?? ($email->tiene_adjuntos
                    ? collect($email->adjuntos_info ?? [])->pluck('filename')->implode(', ')
                    : ''),
                'hash_sha256' => $hashSha256,
                'estado_trabajo' => 'ENVIADO',
            ];
            $radicado = $this->enviadosService->create($radicadoData);

            // Guardar adjuntos del email enviado
            if (!empty($email->adjuntos_info) && $email->tiene_adjuntos) {
                try {
                    $mailbox = $this->imapService->connect();
                    $inbox = $mailbox->inbox();
                    $message = $inbox->messages()
                        ->withBody()
                        ->withBodyStructure()
                        ->findOrFail((int) $email->imap_uid);
                    
                    $directorio = 'radicados_enviados/' . $radicado->num_radicado;
                    
                    foreach ($message->attachments() as $attachment) {
                        $this->imapService->saveAttachment($attachment, $directorio);
                    }
                    
                    $mailbox->disconnect();
                } catch (\Exception $e) {
                    Log::error('EmailRadicacionService: Error al guardar adjuntos del correo enviado', [
                        'email_id' => $email->id,
                        'radicado_id' => $radicado->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Convertir email a PDF y guardar como archivo digital
            $pdfPath = $this->convertEmailToPdf($email, $radicado, 'enviado');

            // Actualizar nom_origi con el nombre del PDF generado
            if ($pdfPath) {
                $radicado->update(['nom_origi' => basename($pdfPath)]);
            }

            // Actualizar tracking
            $email->update([
                'radicado_id' => $radicado->id,
                'estado' => 'radicado',
                'radicado_en' => now(),
            ]);

            // Generar rótulo PNG
            $rotuloPath = $this->rotuloService->generarRotulo([
                'num_radicado' => $numRadicado,
                'fecha_radicado' => $radicado->created_at->format('Y-m-d H:i:s'),
                'remitente_nombre' => $email->remitente_nombre,
                'remitente_email' => $email->remitente_email,
                'asunto' => $email->asunto,
                'clasificacion' => $radicado->clasificacionDocumental?->nom ?? '',
                'codigo_verificacion' => $radicado->cod_verifica ?? '',
                'hash_sha256' => $hashSha256,
            ]);

            // Respuesta automática al remitente original
            $replyResult = false;
            try {
                $replyResult = $this->responderConRadicado(
                    $emailId,
                    'Su correo ha sido radicado exitosamente con el número ' . $numRadicado . '. Adjunto rótulo del radicado.'
                );
            } catch (\Exception $e) {
                \Log::warning('EmailRadicacionService: Error al enviar respuesta automática (enviado)', [
                    'email_id' => $emailId,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'radicado' => $radicado->load(['clasificacionDocumental', 'tercero', 'medioEnvio']),
                'rotulo_path' => $rotuloPath,
                'pdf_path' => $pdfPath,
                'respuesta_enviada' => $replyResult,
            ];
        });
    }

    /**
     * Calcula el hash SHA-256 del contenido del correo (ISO 27001 A.10.1.2).
     */
    protected function calcularHashEmail(VentanillaEmailRadicado $email): string
    {
        $hashContent = implode('|', [
            $email->asunto ?? '',
            $email->remitente_email ?? '',
            $email->remitente_nombre ?? '',
            $email->fecha_correo?->timestamp ?? '',
            $email->body_text ?? '',
        ]);

        return hash('sha256', $hashContent);
    }

    /**
     * Convierte el contenido HTML del correo a PDF y lo guarda como archivo digital.
     *
     * @param  VentanillaEmailRadicado  $email  Registro del correo
     * @param  VentanillaRadicaReci|VentanillaRadicaEnviados  $radicado  Radicado creado
     * @param  string  $tipo  'recibido' o 'enviado'
     * @return string|null Ruta del PDF generado o null si falla
     */
    public function convertEmailToPdf(VentanillaEmailRadicado $email, $radicado, string $tipo = 'recibido'): ?string
    {
        try {
            $htmlContent = $email->body_html ?: '<p>'.nl2br(e($email->body_text)).'</p>';

            $data = [
                'email' => [
                    'asunto' => $email->asunto,
                    'remitente_nombre' => $email->remitente_nombre,
                    'remitente_email' => $email->remitente_email,
                    'fecha_correo' => $email->fecha_correo?->format('Y-m-d H:i:s'),
                    'body_html' => $htmlContent,
                    'adjuntos_info' => $email->adjuntos_info ?? [],
                    'tiene_adjuntos' => $email->tiene_adjuntos,
                ],
                'radicado' => [
                    'num_radicado' => $radicado->num_radicado,
                    'tipo' => $tipo === 'recibido' ? 'Recibido' : 'Enviado',
                ],
                'entidad' => config('app.name', 'OCOBO'),
                'fechaGeneracion' => now()->format('Y-m-d H:i:s'),
            ];

            $filename = 'correo-'.($radicado->num_radicado ?? date('YmdHis')).'.pdf';

            $pdf = $this->pdfService->generarPdfCorreo($data, ['filename' => $filename, 'enable_remote_images' => false]);

            // Guardar en directorio del radicado usando Storage::disk
            $diskName = $tipo === 'recibido' ? 'radicados_recibidos' : 'radicados_enviados';
            $storage = Storage::disk($diskName);
            $directorio = $radicado->num_radicado;

            if (! $storage->exists($directorio)) {
                $storage->makeDirectory($directorio);
            }

            $relativePath = $directorio.'/'.$filename;
            $storage->put($relativePath, $pdf->output());

            // Actualizar archivo_digital en el radicado
            $radicado->update([
                'archivo_digital' => $relativePath,
                'archivo_tipo' => 'application/pdf',
                'archivo_peso' => $storage->size($relativePath),
            ]);

            return $relativePath;
        } catch (\Exception $e) {
            Log::error('EmailRadicacionService: Error al convertir correo a PDF', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Genera el siguiente número de radicado recibido en formato YYYY-VR-NNNNNN.
     */
    public function obtenerSiguienteNumeroRadicado(): string
    {
        return DB::transaction(function () {
            $anio = now()->format('Y');
            $prefijo = $anio.'-VR-';

            $ultimoNumero = DB::table('ventanilla_radica_reci')
                ->where('num_radicado', 'like', $prefijo.'%')
                ->lockForUpdate()
                ->max(DB::raw("CAST(SUBSTRING(num_radicado, ".(strlen($prefijo) + 1).") AS UNSIGNED)"));

            $siguienteNumero = ($ultimoNumero ?? 0) + 1;

            return $prefijo.str_pad($siguienteNumero, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Genera el siguiente número de radicado enviado en formato YYYY-VE-NNNNNN.
     */
    public function obtenerSiguienteNumeroRadicadoEnviado(): string
    {
        return DB::transaction(function () {
            $anio = now()->format('Y');
            $prefijo = $anio.'-VE-';

            $ultimoNumero = DB::table('ventanilla_radica_enviados')
                ->where('num_radicado', 'like', $prefijo.'%')
                ->lockForUpdate()
                ->max(DB::raw("CAST(SUBSTRING(num_radicado, ".(strlen($prefijo) + 1).") AS UNSIGNED)"));

            $siguienteNumero = ($ultimoNumero ?? 0) + 1;

            return $prefijo.str_pad($siguienteNumero, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Obtiene el detalle completo de un correo electrónico.
     */
    public function obtenerDetalleEmail(int $emailId): ?array
    {
        $email = VentanillaEmailRadicado::with('radicado')->find($emailId);

        if (! $email) {
            return null;
        }

        return [
            'id' => $email->id,
            'imap_uid' => $email->imap_uid,
            'asunto' => $email->asunto,
            'remitente_email' => $email->remitente_email,
            'remitente_nombre' => $email->remitente_nombre,
            'fecha_correo' => $email->fecha_correo?->format('Y-m-d H:i:s'),
            'body_text' => $email->body_text,
            'body_html' => $email->body_html,
            'tiene_adjuntos' => $email->tiene_adjuntos,
            'adjuntos_info' => $email->adjuntos_info,
            'estado' => $email->estado,
            'radicado_id' => $email->radicado_id,
            'radicado_num' => $email->radicado?->num_radicado,
            'sincronizado_en' => $email->sincronizado_en?->format('Y-m-d H:i:s'),
            'radicado_en' => $email->radicado_en?->format('Y-m-d H:i:s'),
            'respondido_en' => $email->respondido_en?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtiene el modelo del radicado (recibido o enviado) desde el email tracking.
     */
    protected function obtenerRadicado(VentanillaEmailRadicado $email)
    {
        if (! $email->radicado_id) {
            return null;
        }

        // Intentar recibido
        $recibido = VentanillaRadicaReci::find($email->radicado_id);
        if ($recibido) {
            return $recibido;
        }

        // Intentar enviado
        $enviado = VentanillaRadicaEnviados::find($email->radicado_id);
        if ($enviado) {
            return $enviado;
        }

        return null;
    }

    /**
     * Descarga el rótulo PNG asociado al radicado del correo.
     */
    public function descargarRotulo(int $emailId): ?string
    {
        $email = VentanillaEmailRadicado::find($emailId);

        if (! $email || ! $email->radicado_id) {
            return null;
        }

        // Intentar ambos directorios (recibidos y enviados)
        $directories = ['radicados_recibidos/rotulos', 'radicados_enviados/rotulos'];
        $radicado = $this->obtenerRadicado($email);

        if (! $radicado) {
            return null;
        }

        foreach ($directories as $directorio) {
            $archivos = glob(storage_path("app/{$directorio}/rotulo_*{$radicado->num_radicado}*.png"));
            if (! empty($archivos)) {
                return $archivos[0];
            }
        }

        return null;
    }

    /**
     * Envía un correo de respuesta con el rótulo de radicado adjunto.
     */
    public function responderConRadicado(int $emailId, string $mensajeRespuesta): bool
    {
        try {
            $email = VentanillaEmailRadicado::find($emailId);

            if (! $email || ! $email->radicado_id) {
                throw new \Exception('El correo o el radicado no existen.');
            }

            $radicado = $this->obtenerRadicado($email);

            if (! $radicado) {
                throw new \Exception('No se encontró el radicado asociado.');
            }

            MailConfigHelper::configureFromConfigVarias();

            $rotuloPath = $this->descargarRotulo($emailId);

            $mailData = [
                'radicado' => $radicado,
                'mensaje' => $mensajeRespuesta,
                'email_original' => [
                    'asunto' => $email->asunto,
                    'remitente' => $email->remitente_nombre,
                ],
            ];

            $toEmail = $email->remitente_email;
            $toName = $email->remitente_nombre;

            $mailable = new RespuestaRadicadoMail($mailData);

            if ($rotuloPath && file_exists($rotuloPath)) {
                $mailable->attach($rotuloPath, [
                    'as' => 'rotulo_'.$email->radicado->num_radicado.'.png',
                    'mime' => 'image/png',
                ]);
            }

            Mail::to($toEmail, $toName)->send($mailable);

            $email->update([
                'estado' => 'respondido',
                'respondido_en' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('EmailRadicacionService: Error al enviar respuesta con radicado', [
                'email_id' => $emailId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Guarda los adjuntos del correo en el disco del radicado.
     */
    protected function guardarAdjuntosEmail(VentanillaEmailRadicado $email, VentanillaRadicaReci $radicado): void
    {
        try {
            $mailbox = $this->imapService->connect();
            $inbox = $mailbox->inbox();

            $message = $inbox->messages()
                ->withBody()
                ->withBodyStructure()
                ->findOrFail((int) $email->imap_uid);

            $directorio = 'radicados_recibidos/'.$radicado->num_radicado;

            foreach ($message->attachments() as $attachment) {
                $this->imapService->saveAttachment($attachment, $directorio);
            }

            $mailbox->disconnect();
        } catch (\Exception $e) {
            Log::error('EmailRadicacionService: Error al guardar adjuntos del correo', [
                'email_id' => $email->id,
                'radicado_id' => $radicado->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
