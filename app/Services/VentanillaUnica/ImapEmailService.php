<?php

namespace App\Services\VentanillaUnica;

use App\Helpers\MailConfigHelper;
use App\Models\Configuracion\ConfigVarias;
use DirectoryTree\ImapEngine\Attachment;
use DirectoryTree\ImapEngine\Mailbox;
use DirectoryTree\ImapEngine\MessageInterface;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para conexión y gestión de correos electrónicos vía IMAP.
 * Utiliza la librería ImapEngine para la comunicación con el servidor.
 */
class ImapEmailService
{
    /**
     * Instancia del buzón IMAP.
     */
    protected ?Mailbox $mailbox = null;

    /**
     * Crea y retorna una conexión IMAP configurada desde config_varias.
     *
     * @return Mailbox Instancia del buzón IMAP conectado
     *
     * @throws \Exception Si la configuración está incompleta o falla la conexión
     */
    public function connect(): Mailbox
    {
        $host = ConfigVarias::getValor('correo_imap_host');
        $port = ConfigVarias::getValor('correo_imap_port', '993');
        $encryption = ConfigVarias::getValor('correo_imap_encryption', 'ssl');
        $username = ConfigVarias::getValor('correo_username');
        $password = ConfigVarias::getValor('correo_password');

        if (empty($host) || empty($username) || empty($password)) {
            throw new \Exception('La configuración IMAP está incompleta. Verifique host, usuario y contraseña.');
        }

        $decryptedPassword = MailConfigHelper::decryptPassword($password);

        $this->mailbox = Mailbox::make([
            'host' => $host,
            'port' => (int) $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $decryptedPassword,
            'timeout' => 30,
        ]);

        $this->mailbox->connect();

        return $this->mailbox;
    }

    /**
     * Obtiene los mensajes del buzón de entrada de los últimos N días.
     *
     * @param  int  $daysBack  Cantidad de días hacia atrás para buscar correos
     * @return array Arreglo con los datos de cada mensaje
     */
    public function fetchInboxMessages(int $daysBack = 7): array
    {
        try {
            $mailbox = $this->connect();
            $inbox = $mailbox->inbox();

            $fechaDesde = now()->subDays($daysBack);

            $messages = $inbox->messages()
                ->leaveUnread()
                ->withHeaders()
                ->withBody()
                ->withBodyStructure()
                ->since($fechaDesde)
                ->get();

            $result = [];

            foreach ($messages as $message) {
                $from = $message->from();
                $result[] = [
                    'uid' => (string) $message->uid(),
                    'asunto' => $message->subject() ?: '(Sin asunto)',
                    'remitente_email' => $from?->email() ?? '',
                    'remitente_nombre' => $from?->name() ?? '',
                    'fecha_correo' => $message->date()?->toDateTimeString(),
                    'body_text' => $message->text() ?? '',
                    'body_html' => $message->html() ?? '',
                    'tiene_adjuntos' => $message->hasAttachments(),
                    'adjuntos_info' => $this->getAttachments($message),
                ];
            }

            $mailbox->disconnect();

            return $result;
        } catch (\Exception $e) {
            Log::error('ImapEmailService: Error al obtener mensajes del buzón', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene los detalles completos de un mensaje por su UID.
     *
     * @param  string  $uid  Identificador único del mensaje IMAP
     * @return array|null Arreglo con los datos del mensaje o null si no existe
     */
    public function getMessageDetails(string $uid): ?array
    {
        try {
            $mailbox = $this->connect();
            $inbox = $mailbox->inbox();

            $message = $inbox->messages()
                ->withHeaders()
                ->withBody()
                ->withBodyStructure()
                ->findOrFail((int) $uid);

            $from = $message->from();
            $details = [
                'uid' => (string) $message->uid(),
                'asunto' => $message->subject() ?: '(Sin asunto)',
                'remitente_email' => $from?->email() ?? '',
                'remitente_nombre' => $from?->name() ?? '',
                'fecha_correo' => $message->date()?->toDateTimeString(),
                'body_text' => $message->text() ?? '',
                'body_html' => $message->html() ?? '',
                'tiene_adjuntos' => $message->hasAttachments(),
                'adjuntos_info' => $this->getAttachments($message),
            ];

            $mailbox->disconnect();

            return $details;
        } catch (\Exception $e) {
            Log::error('ImapEmailService: Error al obtener detalles del mensaje', [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extrae la información de los adjuntos de un mensaje.
     *
     * @param  MessageInterface  $message  Instancia del mensaje IMAP
     * @return array Arreglo con la información de cada adjunto
     */
    public function getAttachments(MessageInterface $message): array
    {
        $attachments = [];

        foreach ($message->attachments() as $attachment) {
            $attachments[] = [
                'filename' => $attachment->filename(),
                'content_type' => $attachment->contentType(),
                'size' => strlen($attachment->contents()),
                'extension' => $attachment->extension(),
            ];
        }

        return $attachments;
    }

    /**
     * Guarda un adjunto en el disco.
     *
     * @param  Attachment  $attachment  Instancia del adjunto IMAP
     * @param  string  $directory  Directorio destino dentro del disco
     * @return string Ruta relativa del archivo guardado
     *
     * @throws \Exception Si falla al guardar el archivo
     */
    public function saveAttachment(Attachment $attachment, string $directory): string
    {
        $filename = $attachment->filename();

        if (empty($filename)) {
            $extension = $attachment->extension() ?? 'bin';
            $filename = uniqid('adjunto_', true).'.'.$extension;
        }

        $path = $directory.'/'.$filename;
        $fullPath = storage_path('app/'.$path);

        $directoryPath = dirname($fullPath);
        if (! is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $saved = $attachment->save($fullPath);

        if ($saved === false) {
            throw new \Exception("No se pudo guardar el adjunto: {$filename}");
        }

        return $path;
    }

    /**
     * Marca un mensaje como leído en el servidor IMAP.
     *
     * @param  MessageInterface  $message  Instancia del mensaje IMAP
     */
    public function markAsSeen(MessageInterface $message): void
    {
        try {
            $message->markSeen();
        } catch (\Exception $e) {
            Log::warning('ImapEmailService: Error al marcar mensaje como leído', [
                'uid' => $message->uid(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verifica si la configuración IMAP está completa.
     *
     * @return bool true si la configuración está completa
     */
    public function isConfigured(): bool
    {
        $host = ConfigVarias::getValor('correo_imap_host');
        $username = ConfigVarias::getValor('correo_username');
        $password = ConfigVarias::getValor('correo_password');

        return ! empty($host) && ! empty($username) && ! empty($password);
    }
}
