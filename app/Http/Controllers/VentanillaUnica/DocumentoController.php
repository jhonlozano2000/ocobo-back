<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Traits\ApiResponseTrait;

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
        'avatars'
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
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function verDocumento(Request $request)
    {
        $disk = $request->query('disk');
        $path = $request->query('path');

        if (!$disk || !$path) {
            return $this->errorResponse('Parámetros inválidos', null, 400);
        }

        // Validación estricta de discos permitidos (Path Traversal Protection)
        if (!in_array($disk, self::ALLOWED_DISKS)) {
            return $this->errorResponse('Disco no autorizado', null, 403);
        }

        // Sanitización del path (eliminar intentos de subir directorios ../)
        $path = str_replace(['../', '..\\'], '', $path);

        $storage = Storage::disk($disk);

        if (!$storage->exists($path)) {
            return $this->errorResponse('El documento no existe o fue eliminado', null, 404);
        }

        $mimeType = $storage->mimeType($path);
        
        // Retornar el archivo directamente para ser renderizado en el navegador (ej. <embed> o <iframe>)
        return response()->stream(function () use ($storage, $path) {
            $stream = $storage->readStream($path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }
}
