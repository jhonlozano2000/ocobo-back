<?php

namespace App\Services\Seguridad;

use App\Models\Configuracion\ConfigVarias;
use finfo;
use Symfony\Component\Mime\MimeTypes;
use ZipArchive;

/**
 * Inspección del contenido real de archivos subidos o recibidos por correo
 * (M01-04, M02-04, M03-10, M05-06): extensión prohibida, contenido ejecutable,
 * contenido que no corresponde con la extensión y archivos comprimidos tipo zip-bomb.
 */
class InspectorArchivos
{
    public const MAX_DESCOMPRIMIDO = 500 * 1024 * 1024;

    public const MAX_RATIO = 200;

    public const MAX_ENTRADAS = 10000;

    private const OLE = ['application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint',
        'application/vnd.ms-office', 'application/vnd.ms-outlook', 'application/CDFV2', 'application/x-ole-storage'];

    private const ZIP = ['application/zip', 'application/x-zip-compressed'];

    /**
     * Extensión => tipos MIME detectados (finfo) aceptables para esa extensión.
     * Las extensiones que no estén aquí solo pasan los controles de contenido peligroso.
     */
    public const TIPOS = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'tif' => ['image/tiff'],
        'tiff' => ['image/tiff'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'webp' => ['image/webp'],
        'doc' => self::OLE,
        'xls' => self::OLE,
        'ppt' => self::OLE,
        'msg' => self::OLE,
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', ...self::ZIP],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', ...self::ZIP],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', ...self::ZIP],
        'odt' => ['application/vnd.oasis.opendocument.text', ...self::ZIP],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', ...self::ZIP],
        'odp' => ['application/vnd.oasis.opendocument.presentation', ...self::ZIP],
        'zip' => self::ZIP,
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'xml' => ['text/xml', 'application/xml'],
        'eml' => ['message/rfc822', 'text/plain'],
    ];

    /** Formatos basados en ZIP: se revisan contra zip-bomb. */
    private const EXT_ZIP = ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'zip'];

    /** Nunca se aceptan, aunque estén en la configuración. */
    public const EXT_PELIGROSAS = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht',
        'exe', 'dll', 'com', 'bat', 'cmd', 'msi', 'scr', 'pif', 'cpl', 'lnk', 'sh', 'bash', 'ps1', 'psm1',
        'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh', 'hta', 'jar', 'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz',
        'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'htaccess'];

    private const MIME_PELIGROSOS = ['application/x-dosexec', 'application/x-msdownload', 'application/x-executable',
        'application/x-sharedlib', 'application/x-mach-binary', 'application/x-elf', 'application/x-pie-executable',
        'application/vnd.microsoft.portable-executable', 'application/x-msi', 'application/java-archive',
        'text/x-php', 'application/x-php', 'text/html', 'application/xhtml+xml', 'image/svg+xml',
        'application/javascript', 'text/javascript', 'text/x-shellscript', 'application/x-sh',
        'text/x-python', 'text/x-perl', 'application/hta'];

    /**
     * Extensiones permitidas configuradas (ConfigVarias tipos_archivos_permitidos).
     */
    public static function extensionesConfiguradas(string $default = 'pdf,doc,docx,jpg,jpeg,png,gif'): array
    {
        $valor = (string) ConfigVarias::getValor('tipos_archivos_permitidos', $default);

        return array_values(array_filter(array_map(fn ($e) => strtolower(trim($e, " .\t")), explode(',', $valor))));
    }

    /**
     * Inspecciona un archivo. Devuelve el motivo del rechazo o null si es aceptable.
     *
     * @param  array|null  $extensionesPermitidas  null = no restringir por lista (solo controles de contenido)
     */
    public static function inspeccionar(string $path, string $nombreOriginal, ?array $extensionesPermitidas = null): ?string
    {
        $ext = self::extension($nombreOriginal);

        if (in_array($ext, self::EXT_PELIGROSAS, true)) {
            return "El tipo de archivo .{$ext} no está permitido.";
        }

        if ($extensionesPermitidas !== null && ! in_array($ext, $extensionesPermitidas, true)) {
            return $ext === '' ? 'El archivo no tiene extensión.' : "El formato .{$ext} no está permitido.";
        }

        if (! is_file($path) || filesize($path) === 0) {
            return 'El archivo está vacío o no se pudo leer.';
        }

        $mime = self::mimeReal($path);

        if ($mime === '') {
            return 'El archivo está vacío o no se pudo leer.';
        }

        if (in_array($mime, self::MIME_PELIGROSOS, true)) {
            return 'El contenido del archivo no está permitido.';
        }

        if (isset(self::TIPOS[$ext]) && ! in_array($mime, self::TIPOS[$ext], true)) {
            return "El contenido del archivo no corresponde con su extensión .{$ext}.";
        }

        if (in_array($ext, self::EXT_ZIP, true) || in_array($mime, self::ZIP, true)) {
            return self::revisarZip($path);
        }

        return null;
    }

    /**
     * Extensión con la que se guarda el archivo: la del cliente si su contenido la respalda;
     * si no, la que corresponde al contenido real (nunca una extensión ejecutable).
     */
    public static function extensionSegura(string $path, string $nombreOriginal): string
    {
        $ext = self::extension($nombreOriginal);

        if ($ext !== '' && self::inspeccionar($path, $nombreOriginal) === null) {
            return $ext;
        }

        $sugerida = is_file($path) ? (MimeTypes::getDefault()->getExtensions(self::mimeReal($path))[0] ?? null) : null;

        return $sugerida && ! in_array($sugerida, self::EXT_PELIGROSAS, true) ? $sugerida : 'bin';
    }

    /**
     * MIME según el contenido; '' si no se puede leer (p. ej. el antivirus del servidor bloqueó el archivo),
     * para que quien llama lo rechace en vez de fallar con una excepción.
     */
    public static function mimeReal(string $path): string
    {
        return (string) @(new finfo(FILEINFO_MIME_TYPE))->file($path);
    }

    private static function extension(string $nombre): string
    {
        $ext = strtolower((string) pathinfo(str_replace('\\', '/', $nombre), PATHINFO_EXTENSION));

        return substr(preg_replace('/[^a-z0-9]/', '', $ext) ?? '', 0, 10);
    }

    private static function revisarZip(string $path): ?string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return 'El archivo comprimido está dañado o no se puede leer.';
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRADAS) {
                return 'El archivo comprimido contiene demasiados archivos.';
            }

            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    return 'El archivo comprimido está dañado o no se puede leer.';
                }

                $total += $stat['size'];
                $ratio = $stat['comp_size'] > 0 ? $stat['size'] / $stat['comp_size'] : 0;

                if ($total > self::MAX_DESCOMPRIMIDO || ($stat['size'] > 1024 * 1024 && $ratio > self::MAX_RATIO)) {
                    return 'El archivo comprimido excede el tamaño descomprimido permitido.';
                }
            }

            return null;
        } finally {
            $zip->close();
        }
    }
}
