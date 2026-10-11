<?php

namespace App\Services\VentanillaUnica;

use App\Helpers\MailConfigHelper;
use App\Models\Configuracion\ConfigVarias;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\Seguridad\InspectorArchivos;
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
     * Obtiene emails del inbox con paginación nativa de IMAP.
     * Lee directamente de Gmail sin pasar por la BD.
     *
     * @param  int  $page  Página actual (1-based)
     * @param  int  $perPage  Elementos por página
     * @param  string|null  $search  Término de búsqueda (asunto o remitente)
     * @return array ['emails' => array, 'pagination' => array]
     */
    public function fetchPaginatedInbox(int $page = 1, int $perPage = 15, ?string $search = null): array
    {
        try {
            $mailbox = $this->connect();
            $inbox = $mailbox->inbox();

            $query = $inbox->messages()
                ->withHeaders()
                ->withBodyStructure()
                ->newest();

            if ($search) {
                $escapedSearch = addcslashes($search, '\()"');
                $query->where('SUBJECT', $escapedSearch)
                    ->orWhere('FROM', $escapedSearch);
            }

            $paginator = $query->paginate($perPage, $page);

            $emails = [];
            foreach ($paginator->items() as $message) {
                $from = $message->from();
                $emails[] = [
                    'id' => (string) $message->uid(),
                    'uid' => (string) $message->uid(),
                    'asunto' => $message->subject() ?: '(Sin asunto)',
                    'remitente_email' => $from?->email() ?? '',
                    'remitente_nombre' => $from?->name() ?? '',
                    'fecha_correo' => $message->date()?->toDateTimeString(),
                    'body_text' => $message->text() ?? '',
                    'body_html' => $message->html() ?? '',
                    'tiene_adjuntos' => $message->hasAttachments(),
                    'adjuntos_info' => $this->getAttachments($message),
                    'is_read' => in_array('Seen', $message->flags() ?? []),
                    'is_starred' => in_array('Flagged', $message->flags() ?? []),
                    'is_radicado' => false,
                    'folder' => 'inbox',
                    'labels' => [],
                ];
            }

            $mailbox->disconnect();

            $this->marcarRadicados($emails);

            return [
                'emails' => $emails,
                'pagination' => [
                    'currentPage' => $paginator->currentPage(),
                    'lastPage' => $paginator->lastPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('ImapEmailService: Error al obtener emails paginados del inbox', [
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

    /** Tamaño mínimo aceptado para adjuntos de correo (límite habitual de los servidores). */
    public const MIN_MAX_ADJUNTO = 25 * 1024 * 1024;

    /**
     * Guarda un adjunto en el disco.
     *
     * M05-06: se rechazan (sin guardar) los adjuntos que superan el tamaño máximo, tienen
     * contenido ejecutable, no corresponden con su extensión o son zip-bomb. El correo
     * original sigue en el buzón IMAP.
     *
     * @param  Attachment  $attachment  Instancia del adjunto IMAP
     * @param  string  $directory  Directorio destino dentro del disco
     * @return string|null Ruta relativa del archivo guardado, o null si fue rechazado
     *
     * @throws \Exception Si falla al guardar el archivo
     */
    public function saveAttachment(Attachment $attachment, string $directory): ?string
    {
        $filename = self::nombreSeguroAdjunto($attachment->filename(), $attachment->extension());

        $tamano = $attachment->contentStream()->getSize() ?? strlen($attachment->contents());
        if ($tamano > self::maxBytesAdjunto()) {
            Log::warning('ImapEmailService: adjunto rechazado por tamaño', [
                'nombre' => $filename,
                'bytes' => $tamano,
                'max_bytes' => self::maxBytesAdjunto(),
            ]);

            return null;
        }

        $baseDir = storage_path('app/'.$directory);
        if (! is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        // Sin sobrescribir: dos adjuntos con el mismo nombre (o uno llamado como el PDF del correo)
        $nombre = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        for ($i = 1; file_exists($baseDir.'/'.$filename); $i++) {
            $filename = $nombre.'_'.$i.($ext !== '' ? '.'.$ext : '');
        }

        $fullPath = $baseDir.'/'.$filename;
        if (dirname($fullPath) !== $baseDir) {
            throw new \Exception('Nombre de adjunto inválido.');
        }

        $saved = $attachment->save($fullPath);

        if ($saved === false) {
            throw new \Exception("No se pudo guardar el adjunto: {$filename}");
        }

        $error = InspectorArchivos::inspeccionar($fullPath, $filename);
        if ($error !== null) {
            @unlink($fullPath);
            Log::warning('ImapEmailService: adjunto rechazado por inspección de contenido', [
                'nombre' => $filename,
                'directorio' => $directory,
                'motivo' => $error,
            ]);

            return null;
        }

        return $directory.'/'.$filename;
    }

    /**
     * Tamaño máximo de un adjunto de correo: max_tamano_archivo (KB) o 25 MB, el mayor.
     */
    public static function maxBytesAdjunto(): int
    {
        return max((int) ConfigVarias::getValor('max_tamano_archivo', 10240) * 1024, self::MIN_MAX_ADJUNTO);
    }

    /**
     * M05-05: nombre de archivo seguro a partir del nombre del header MIME (lo controla
     * el remitente). Se queda solo con el último segmento, sin separadores, caracteres
     * de control ni puntos iniciales, y con longitud limitada.
     */
    public static function nombreSeguroAdjunto(?string $filename, ?string $extension = null): string
    {
        $nombre = basename(str_replace('\\', '/', (string) $filename));
        $nombre = preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]/u', '', $nombre) ?? '';
        $nombre = trim(ltrim($nombre, '. '));

        if ($nombre === '') {
            $ext = preg_replace('/[^A-Za-z0-9]/', '', (string) $extension) ?: 'bin';

            return uniqid('adjunto_', true).'.'.$ext;
        }

        if (mb_strlen($nombre) > 150) {
            $ext = mb_substr(pathinfo($nombre, PATHINFO_EXTENSION), 0, 10);
            $nombre = mb_substr(pathinfo($nombre, PATHINFO_FILENAME), 0, 139).($ext !== '' ? '.'.$ext : '');
        }

        return $nombre;
    }

    /**
     * Marca los emails que ya tienen radicado asociado.
     * Busca en ventanilla_radica_reci por imap_uid y cambia folder a 'sent'.
     *
     * @param  array  &$emails  Array de emails (se modifica por referencia)
     */
    protected function marcarRadicados(array &$emails): void
    {
        $uids = array_column($emails, 'uid');

        if (empty($uids)) {
            return;
        }

        $radicadosUids = VentanillaRadicaReci::whereIn('imap_uid', $uids)
            ->pluck('imap_uid')
            ->toArray();

        foreach ($emails as &$email) {
            $email['is_radicado'] = in_array((string) $email['uid'], array_map('strval', $radicadosUids));
        }
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
