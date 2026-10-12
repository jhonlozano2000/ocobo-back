<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AccesoRadicado;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosArchivos;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoArchivos;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciArchivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador para visualización segura de documentos
 * OWASP A01:2021 - Broken Access Control
 * ISO 27001 A.12.2.1 - Information exchange
 */
class DocumentoController extends Controller
{
    use ApiResponseTrait;

    /**
     * Discos permitidos para visualizar documentos por seguridad.
     */
    private const ALLOWED_DISKS = [
        'radicados_recibidos',
        'radicados_enviados',
        'radicados_internos',
        'firmas',
        'avatars',
    ];

    /**
     * Extensiones permitidas para visualización inline.
     */
    private const ALLOWED_EXTENSIONS = [
        'pdf', 'png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp', 'svg',
    ];

    /**
     * Constructor con Middleware de Autenticación.
     * Toda previsualización de documento requiere un usuario logueado (Sanctum).
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Sirve el archivo para visualización en línea (Inline).
     *
     * @return StreamedResponse|JsonResponse
     */
    public function verDocumento(Request $request)
    {
        $disk = $request->query('disk');
        $path = $request->query('path');

        if (! $disk || ! $path) {
            return $this->badRequestResponse('Parámetros inválidos');
        }

        // Validación estricta de discos permitidos (Path Traversal Protection)
        if (! in_array($disk, self::ALLOWED_DISKS)) {
            Log::warning('DocumentoController: Intento de acceso a disco no autorizado', [
                'disk' => $disk,
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            return $this->forbiddenResponse('Disco no autorizado');
        }

        // Sanitización robusta del path ( OWASP A01 - Path Traversal)
        $path = $this->sanitizarPath($path);

        if (! $path) {
            Log::warning('DocumentoController: Path no válido después de sanitización', [
                'path_original' => $request->query('path'),
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            return $this->badRequestResponse('Ruta de documento no válida');
        }

        // RBAC-003: el archivo debe pertenecer a algo que el usuario pueda ver
        if (! $this->puedeVer($request->user(), $disk, $path)) {
            return $this->notFoundResponse('El documento no existe o fue eliminado');
        }

        $storage = Storage::disk($disk);

        // Verificar que el archivo existe
        if (! $storage->exists($path)) {
            return $this->notFoundResponse('El documento no existe o fue eliminado');
        }

        // Verificar que la extensión sea permitida
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS)) {
            Log::warning('DocumentoController: Extensión de archivo no permitida', [
                'extension' => $extension,
                'path' => $path,
                'user_id' => auth()->id(),
            ]);

            return $this->forbiddenResponse('Tipo de archivo no permitido para visualización');
        }

        $mimeType = $storage->mimeType($path);

        // Validar que el MIME type sea seguro
        if (! $this->esMimeTypePermitido($mimeType)) {
            Log::warning('DocumentoController: MIME type no permitido', [
                'mime' => $mimeType,
                'path' => $path,
                'user_id' => auth()->id(),
            ]);

            return $this->forbiddenResponse('Tipo de contenido no permitido');
        }

        // Obtener el nombre base seguro para el Content-Disposition
        $safeFilename = $this->sanitizarNombreArchivo(basename($path));

        // Headers de seguridad adicionales
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$safeFilename.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
        ];

        return response()->stream(function () use ($storage, $path) {
            $stream = $storage->readStream($path);
            if ($stream === false) {
                Log::error('DocumentoController: Error al leer stream', ['path' => $path]);

                return;
            }
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }

    /**
     * Sanitiza un path para prevenir path traversal.
     * OWASP A01:2021
     *
     * @return string|null Path sanitizado o null si es inseguro
     */
    /**
     * Autorización por propietario (RBAC-003). Antes cualquier usuario autenticado podía leer
     * cualquier archivo de los discos permitidos conociendo su path.
     * - avatars: visibles para cualquier usuario autenticado.
     * - firmas: solo la propia, o quien administra usuarios.
     * - radicados_*: el path debe ser el digital o un adjunto de un radicado del módulo que el
     *   usuario pueda mostrar (permiso Mostrar + ABAC, igual que acceso_radicado).
     */
    private function puedeVer(User $user, string $disk, string $path): bool
    {
        if ($disk === 'avatars') {
            return true;
        }

        if ($disk === 'firmas') {
            return $user->firma === $path || $user->can('Control de acceso - Usuarios -> Editar');
        }

        [$tipo, $modulo, $radicados, $adjuntos, $fk] = match ($disk) {
            'radicados_recibidos' => ['recibido', 'Recibida', VentanillaRadicaReci::class, VentanillaRadicaReciArchivo::class, 'radicado_id'],
            'radicados_enviados' => ['enviado', 'Enviada', VentanillaRadicaEnviados::class, VentanillaRadicaEnviadosArchivos::class, 'radica_enviado_id'],
            'radicados_internos' => ['interno', 'Interna', VentanillaRadicaInterno::class, VentanillaRadicaInternoArchivos::class, 'radicado_id'],
        };

        if (! $user->can("Radicar -> Cores. {$modulo} -> Mostrar")) {
            return false;
        }

        $ids = $radicados::where('archivo_digital', $path)->pluck('id')
            ->merge($adjuntos::where('archivo', $path)->pluck($fk))
            ->unique();

        return $ids->contains(fn ($id) => AccesoRadicado::permitido($user, $tipo, $id));
    }

    private function sanitizarPath(string $path): ?string
    {
        // 1. Eliminar null bytes
        $path = str_replace("\0", '', $path);

        // 2. Decodificar URL encoding dos veces (protección contra doble encoding)
        $path = urldecode(urldecode($path));

        // 3. Verificar null bytes otra vez (después de decoding)
        if (strpos($path, "\0") !== false) {
            return null;
        }

        // 4. Normalizar slashes
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path = str_replace([DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR], DIRECTORY_SEPARATOR, $path);

        // 5. Eliminar secuencias peligrosas
        $patronesPeligrosos = [
            '../',
            '..\\',
            '/../',
            '\\..\\',
            '%2e%2e%2f', // .. encoded
            '%2e%2e/',
            '..%2f',
            '%2e%2e%5c', // ..\ encoded
            '..%5c',
        ];

        $pathLower = strtolower($path);
        foreach ($patronesPeligrosos as $patron) {
            if (strpos($pathLower, strtolower($patron)) !== false) {
                Log::warning('DocumentoController: Patrón peligroso detectado', [
                    'pattern' => $patron,
                    'path' => $path,
                ]);

                return null;
            }
        }

        // 6. Verificar que no sea una ruta absoluta externa
        if (preg_match('/^[a-zA-Z]:\\\\|^\//', $path)) {
            return null;
        }

        // 7. Verificar longitud máxima
        if (strlen($path) > 500) {
            return null;
        }

        // 8. Obtener el realpath y verificar que esté dentro del storage
        // Nota: Esta verificación puede fallar si el archivo no existe aún

        return trim($path, '/\\');
    }

    /**
     * Verifica si el MIME type es permitido.
     */
    private function esMimeTypePermitido(string $mimeType): bool
    {
        $mimeTypesPermitidos = [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/bmp',
            'image/webp',
            'image/svg+xml',
        ];

        return in_array($mimeType, $mimeTypesPermitidos);
    }

    /**
     * Sanitiza el nombre de archivo para Content-Disposition.
     */
    private function sanitizarNombreArchivo(string $filename): string
    {
        // Eliminar cualquier path
        $filename = basename($filename);

        // Reemplazar caracteres peligrosos
        $filename = preg_replace('/[^\w\.\-]/', '_', $filename);

        // Limitar longitud
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $nombre = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($nombre, 0, 250).'.'.$extension;
        }

        return $filename;
    }
}
