<?php

namespace App\Services\VentanillaUnica;

use App\Helpers\MailConfigHelper;
use App\Mail\RespuestaRadicadoMail;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\Configuracion\ConfigVarias;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio que orquesta la radicación de correos electrónicos.
 * Recibe datos del email directamente (desde IMAP) y crea radicados en ventanilla_radica_reci/enviados.
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
     * Crea un radicado RECIBIDO a partir de datos de un email.
     *
     * @param  array  $emailData  Datos del email (uid, asunto, remitente_*, fecha_correo, body_*, adjuntos_info, etc.)
     * @param  array  $data  Datos del formulario de radicación
     * @return array ['radicado', 'rotulo_path', 'pdf_path', 'respuesta_enviada']
     */
    public function radicarFromEmailData(array $emailData, array $data = []): array
    {
        return DB::transaction(function () use ($emailData, $data) {
            $hashSha256 = $this->calcularHashEmailArray($emailData);
            $numRadicado = $this->obtenerSiguienteNumeroRadicado();

            $medioRecepId = $data['medio_recep_id'] ?? null;
            if (! $medioRecepId) {
                $medioRecepcion = ConfigListaDetalle::where('nombre', 'Correo Electrónico')->first();
                $medioRecepId = $medioRecepcion?->id;
            }

            if (! $medioRecepId) {
                throw new \Exception('No se encontró el medio de recepción "Correo Electrónico" en la configuración.');
            }

            $adjuntosInfo = $emailData['adjuntos_info'] ?? [];
            $fecDocu = isset($emailData['fecha_correo'])
                ? \Carbon\Carbon::parse($emailData['fecha_correo'])->format('Y-m-d')
                : now()->format('Y-m-d');

            $radicadoData = [
                'num_radicado' => $numRadicado,
                'imap_uid' => $emailData['uid'] ?? null,
                'clasifica_documen_id' => $data['clasifica_documen_id'] ?? null,
                'tercero_id' => $data['tercero_id'] ?? null,
                'usuario_crea' => auth()->id(),
                'medio_recep_id' => $medioRecepId,
                'asunto' => $data['asunto'] ?? $emailData['asunto'] ?? '',
                'nom_origi' => $data['nom_razo_soci'] ?? $emailData['remitente_nombre'] ?? '',
                'fec_docu' => $fecDocu,
                'num_folios' => $data['num_folios'] ?? 0,
                'num_anexos' => $data['num_anexos'] ?? count($adjuntosInfo),
                'descrip_anexos' => $data['descrip_anexos'] ?? (
                    ! empty($adjuntosInfo) ? collect($adjuntosInfo)->pluck('filename')->implode(', ') : null
                ),
                'estado_trabajo' => 'RECIBIDO',
                'cod_verifica' => strtoupper(substr(uniqid('OCOBO-'), 0, 10)),
                'hash_sha256' => $hashSha256,
            ];

            $radicado = $this->reciService->create($radicadoData);

            // Guardar adjuntos desde IMAP
            if (! empty($emailData['tiene_adjuntos']) && ! empty($adjuntosInfo)) {
                $this->guardarAdjuntosDesdeImap($emailData['uid'], $radicado);
            }

            // Convertir email a PDF
            $emailObj = (object) $emailData;
            $pdfPath = $this->convertEmailToPdfArray($emailData, $radicado, 'recibido');

            if ($pdfPath) {
                $radicado->update(['nom_origi' => basename($pdfPath)]);
            }

            // Generar rótulo PNG
            $rotuloPath = $this->rotuloService->generarRotulo([
                'num_radicado' => $numRadicado,
                'fecha_radicado' => $radicado->created_at->format('Y-m-d H:i:s'),
                'remitente_nombre' => $emailData['remitente_nombre'] ?? '',
                'remitente_email' => $emailData['remitente_email'] ?? '',
                'asunto' => $emailData['asunto'] ?? '',
                'clasificacion' => $radicado->clasificacionDocumental?->nom ?? '',
                'codigo_verificacion' => $radicado->cod_verifica,
                'hash_sha256' => $hashSha256,
            ]);

            // Respuesta automática al remitente
            $replyResult = false;
            try {
                $replyResult = $this->responderConRadicadoDirecto(
                    $emailData,
                    'Su correo ha sido radicado exitosamente con el número '.$numRadicado.'. Adjunto rótulo del radicado.'
                );
            } catch (\Exception $e) {
                Log::warning('EmailRadicacionService: Error al enviar respuesta automática', [
                    'uid' => $emailData['uid'] ?? '',
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
     * Crea un radicado ENVIADO a partir de datos de un email.
     *
     * @param  array  $emailData  Datos del email
     * @param  array  $data  Datos del formulario de radicación
     * @return array ['radicado', 'rotulo_path', 'pdf_path', 'respuesta_enviada']
     */
    public function radicarEnviadoFromEmailData(array $emailData, array $data = []): array
    {
        return DB::transaction(function () use ($emailData, $data) {
            $hashSha256 = $this->calcularHashEmailArray($emailData);
            $numRadicado = $this->obtenerSiguienteNumeroRadicadoEnviado();

            $medioEnvioId = $data['medio_enviado_id'] ?? null;
            if (! $medioEnvioId) {
                $medioEnvio = ConfigListaDetalle::where('nombre', 'Correo Electrónico')->first();
                $medioEnvioId = $medioEnvio?->id;
            }

            $tipoRespuestaId = $data['tipo_respuesta_id'] ?? null;
            $adjuntosInfo = $emailData['adjuntos_info'] ?? [];
            $fecDocu = isset($emailData['fecha_correo'])
                ? \Carbon\Carbon::parse($emailData['fecha_correo'])->format('Y-m-d')
                : now()->format('Y-m-d');

            $radicadoData = [
                'num_radicado' => $numRadicado,
                'clasifica_documen_id' => $data['clasifica_documen_id'] ?? null,
                'tercero_id' => $data['tercero_id'] ?? null,
                'usuario_crea' => auth()->id(),
                'medio_enviado_id' => $medioEnvioId,
                'tipo_respuesta_id' => $tipoRespuestaId,
                'asunto' => $data['asunto'] ?? $emailData['asunto'] ?? '',
                'nom_origi' => $data['nom_razo_soci'] ?? $emailData['remitente_nombre'] ?? '',
                'fec_docu' => $fecDocu,
                'num_folios' => $data['num_folios'] ?? 0,
                'num_anexos' => $data['num_anexos'] ?? count($adjuntosInfo),
                'descrip_anexos' => $data['descrip_anexos'] ?? (
                    ! empty($adjuntosInfo) ? collect($adjuntosInfo)->pluck('filename')->implode(', ') : ''
                ),
                'hash_sha256' => $hashSha256,
                'estado_trabajo' => 'ENVIADO',
            ];

            $radicado = $this->enviadosService->create($radicadoData);

            // Guardar adjuntos desde IMAP
            if (! empty($emailData['tiene_adjuntos']) && ! empty($adjuntosInfo)) {
                try {
                    $mailbox = $this->imapService->connect();
                    $inbox = $mailbox->inbox();
                    $message = $inbox->messages()
                        ->withBody()
                        ->withBodyStructure()
                        ->findOrFail((int) $emailData['uid']);

                    $directorio = 'radicados_enviados/'.$radicado->num_radicado;

                    foreach ($message->attachments() as $attachment) {
                        $this->imapService->saveAttachment($attachment, $directorio);
                    }

                    $mailbox->disconnect();
                } catch (\Exception $e) {
                    Log::error('EmailRadicacionService: Error al guardar adjuntos del correo enviado', [
                        'uid' => $emailData['uid'] ?? '',
                        'radicado_id' => $radicado->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Convertir email a PDF
            $pdfPath = $this->convertEmailToPdfArray($emailData, $radicado, 'enviado');

            if ($pdfPath) {
                $radicado->update(['nom_origi' => basename($pdfPath)]);
            }

            // Generar rótulo PNG
            $rotuloPath = $this->rotuloService->generarRotulo([
                'num_radicado' => $numRadicado,
                'fecha_radicado' => $radicado->created_at->format('Y-m-d H:i:s'),
                'remitente_nombre' => $emailData['remitente_nombre'] ?? '',
                'remitente_email' => $emailData['remitente_email'] ?? '',
                'asunto' => $emailData['asunto'] ?? '',
                'clasificacion' => $radicado->clasificacionDocumental?->nom ?? '',
                'codigo_verificacion' => $radicado->cod_verifica ?? '',
                'hash_sha256' => $hashSha256,
            ]);

            // Respuesta automática
            $replyResult = false;
            try {
                $replyResult = $this->responderConRadicadoDirecto(
                    $emailData,
                    'Su correo ha sido radicado exitosamente con el número '.$numRadicado.'. Adjunto rótulo del radicado.'
                );
            } catch (\Exception $e) {
                Log::warning('EmailRadicacionService: Error al enviar respuesta automática (enviado)', [
                    'uid' => $emailData['uid'] ?? '',
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
     * Calcula el hash SHA-256 del contenido del correo desde un array.
     */
    public function calcularHashEmailArray(array $emailData): string
    {
        $timestamp = isset($emailData['fecha_correo'])
            ? \Carbon\Carbon::parse($emailData['fecha_correo'])->timestamp
            : '';

        $hashContent = implode('|', [
            $emailData['asunto'] ?? '',
            $emailData['remitente_email'] ?? '',
            $emailData['remitente_nombre'] ?? '',
            $timestamp,
            $emailData['body_text'] ?? '',
        ]);

        return hash('sha256', $hashContent);
    }

    /**
     * Convierte el contenido del correo a PDF desde un array.
     */
    public function convertEmailToPdfArray(array $emailData, $radicado, string $tipo = 'recibido'): ?string
    {
        try {
            $htmlContent = ($emailData['body_html'] ?? '') ?: '<p>'.nl2br(e($emailData['body_text'] ?? '')).'</p>';

            $fechaCorreo = isset($emailData['fecha_correo'])
                ? \Carbon\Carbon::parse($emailData['fecha_correo'])->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s');

            $data = [
                'email' => [
                    'asunto' => $emailData['asunto'] ?? '',
                    'remitente_nombre' => $emailData['remitente_nombre'] ?? '',
                    'remitente_email' => $emailData['remitente_email'] ?? '',
                    'fecha_correo' => $fechaCorreo,
                    'body_html' => $htmlContent,
                    'adjuntos_info' => $emailData['adjuntos_info'] ?? [],
                    'tiene_adjuntos' => $emailData['tiene_adjuntos'] ?? false,
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

            $diskName = $tipo === 'recibido' ? 'radicados_recibidos' : 'radicados_enviados';
            $storage = Storage::disk($diskName);
            $directorio = $radicado->num_radicado;

            if (! $storage->exists($directorio)) {
                $storage->makeDirectory($directorio);
            }

            $relativePath = $directorio.'/'.$filename;
            $storage->put($relativePath, $pdf->output());

            $radicado->update([
                'archivo_digital' => $relativePath,
                'archivo_tipo' => 'application/pdf',
                'archivo_peso' => $storage->size($relativePath),
            ]);

            return $relativePath;
        } catch (\Exception $e) {
            Log::error('EmailRadicacionService: Error al convertir correo a PDF', [
                'uid' => $emailData['uid'] ?? '',
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Genera el siguiente número de radicado recibido.
     * Lee el formato desde config_varias (formato_num_radicado_reci), ej: YYYYMMDD-#####.
     */
    public function obtenerSiguienteNumeroRadicado(): string
    {
        return DB::transaction(function () {
            $formato = ConfigVarias::getValor('formato_num_radicado_reci', 'YYYYMMDD-#####');

            $ahora = now();
            $anio = $ahora->format('Y');
            $mes = $ahora->format('m');
            $dia = $ahora->format('d');

            $posHash = strpos($formato, '#');
            if ($posHash === false) {
                $prefijoConGuion = str_replace(['YYYY', 'MM', 'DD'], [$anio, $mes, $dia], $formato).'-';
            } else {
                $prefijoConGuion = str_replace(
                    ['YYYY', 'MM', 'DD'],
                    [$anio, $mes, $dia],
                    substr($formato, 0, $posHash)
                );
            }

            $digitCount = 0;
            $i = $posHash;
            $len = strlen($formato);
            while ($i < $len && $formato[$i] === '#') {
                $digitCount++;
                $i++;
            }
            if ($digitCount === 0) {
                $digitCount = 5;
            }

            $ultimoNumero = DB::table('ventanilla_radica_reci')
                ->where('num_radicado', 'like', $prefijoConGuion.'%')
                ->lockForUpdate()
                ->max(DB::raw('CAST(SUBSTRING(num_radicado, '.(strlen($prefijoConGuion) + 1).') AS UNSIGNED)'));

            $siguienteNumero = ($ultimoNumero ?? 0) + 1;

            return $prefijoConGuion.str_pad($siguienteNumero, $digitCount, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Genera el siguiente número de radicado enviado.
     * Lee el formato desde config_varias (formato_num_radicado_env), ej: YYYYMMDD-#####.
     */
    public function obtenerSiguienteNumeroRadicadoEnviado(): string
    {
        return DB::transaction(function () {
            $formato = ConfigVarias::getValor('formato_num_radicado_env', 'YYYYMMDD-#####');

            $ahora = now();
            $anio = $ahora->format('Y');
            $mes = $ahora->format('m');
            $dia = $ahora->format('d');

            $posHash = strpos($formato, '#');
            if ($posHash === false) {
                $prefijoConGuion = str_replace(['YYYY', 'MM', 'DD'], [$anio, $mes, $dia], $formato).'-';
            } else {
                $prefijoConGuion = str_replace(
                    ['YYYY', 'MM', 'DD'],
                    [$anio, $mes, $dia],
                    substr($formato, 0, $posHash)
                );
            }

            $digitCount = 0;
            $i = $posHash;
            $len = strlen($formato);
            while ($i < $len && $formato[$i] === '#') {
                $digitCount++;
                $i++;
            }
            if ($digitCount === 0) {
                $digitCount = 5;
            }

            $ultimoNumero = DB::table('ventanilla_radica_enviados')
                ->where('num_radicado', 'like', $prefijoConGuion.'%')
                ->lockForUpdate()
                ->max(DB::raw('CAST(SUBSTRING(num_radicado, '.(strlen($prefijoConGuion) + 1).') AS UNSIGNED)'));

            $siguienteNumero = ($ultimoNumero ?? 0) + 1;

            return $prefijoConGuion.str_pad($siguienteNumero, $digitCount, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Envía un correo de respuesta con el rótulo de radicado adjunto.
     * Recibe datos del email directamente (array).
     */
    public function responderConRadicadoDirecto(array $emailData, string $mensajeRespuesta): bool
    {
        try {
            $numRadicado = $this->obtenerSiguienteNumeroRadicado();

            // Buscar el radicado más reciente del remitente
            $radicado = VentanillaRadicaReci::where('nom_origi', 'like', '%'.$emailData['remitente_email'].'%')
                ->orWhere('asunto', $emailData['asunto'] ?? '')
                ->latest()
                ->first();

            if (! $radicado) {
                Log::warning('EmailRadicacionService: No se encontró radicado para responder');
                return false;
            }

            MailConfigHelper::configureFromConfigVarias();

            $rotuloPath = $this->descargarRotuloPorNum($radicado->num_radicado);

            $mailData = [
                'radicado' => $radicado,
                'mensaje' => $mensajeRespuesta,
                'email_original' => [
                    'asunto' => $emailData['asunto'] ?? '',
                    'remitente' => $emailData['remitente_nombre'] ?? '',
                ],
            ];

            $toEmail = $emailData['remitente_email'] ?? '';
            $toName = $emailData['remitente_nombre'] ?? '';

            $mailable = new RespuestaRadicadoMail($mailData);

            if ($rotuloPath && file_exists($rotuloPath)) {
                $mailable->attach($rotuloPath, [
                    'as' => 'rotulo_'.$radicado->num_radicado.'.png',
                    'mime' => 'image/png',
                ]);
            }

            Mail::to($toEmail, $toName)->send($mailable);

            return true;
        } catch (\Exception $e) {
            Log::error('EmailRadicacionService: Error al enviar respuesta con radicado', [
                'email' => $emailData['remitente_email'] ?? '',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Descarga el rótulo PNG por número de radicado.
     */
    public function descargarRotuloPorNum(string $numRadicado): ?string
    {
        $directories = ['radicados_recibidos/rotulos', 'radicados_enviados/rotulos'];

        foreach ($directories as $directorio) {
            $archivos = glob(storage_path("app/{$directorio}/rotulo_*{$numRadicado}*.png"));
            if (! empty($archivos)) {
                return $archivos[0];
            }
        }

        return null;
    }

    /**
     * Guarda los adjuntos del correo desde IMAP usando el UID.
     */
    protected function guardarAdjuntosDesdeImap(string $uid, VentanillaRadicaReci $radicado): void
    {
        try {
            $mailbox = $this->imapService->connect();
            $inbox = $mailbox->inbox();

            $message = $inbox->messages()
                ->withBody()
                ->withBodyStructure()
                ->findOrFail((int) $uid);

            $directorio = 'radicados_recibidos/'.$radicado->num_radicado;

            foreach ($message->attachments() as $attachment) {
                $this->imapService->saveAttachment($attachment, $directorio);
            }

            $mailbox->disconnect();
        } catch (\Exception $e) {
            Log::error('EmailRadicacionService: Error al guardar adjuntos del correo', [
                'uid' => $uid,
                'radicado_id' => $radicado->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
