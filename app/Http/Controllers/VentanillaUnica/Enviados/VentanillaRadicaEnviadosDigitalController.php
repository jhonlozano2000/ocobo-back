<?php

namespace App\Http\Controllers\VentanillaUnica\Enviados;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\Enviados\UploadArchivoEnviadoRequest;
use App\Helpers\ArchivoHelper;
use App\Helpers\FileMetadataHelper;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosArchivoEliminado;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VentanillaRadicaEnviadosDigitalController extends Controller
{
    use ApiResponseTrait;

    private const DISK = 'radicados_enviados';
    private const PERM = 'Radicar -> Cores. Enviada -> ';

    public function __construct()
    {
        $this->middleware('can:' . self::PERM . 'Subir digital')->only(['upload']);
        $this->middleware('can:' . self::PERM . 'Eliminar digital')->only(['deleteFile']);
        $this->middleware('can:' . self::PERM . 'Mostrar')->only(['getFileInfo', 'download', 'historialEliminaciones', 'getOcr']);
    }

    public function upload($id, UploadArchivoEnviadoRequest $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $archivo = $request->file('archivo_digital');
            $archivoActual = $radicado->archivo_digital;

            $metadatosInternos = [
                'titulo' => $radicado->num_radicado,
                'autor' => $radicado->terceroEnviado ? ($radicado->terceroEnviado->nom_razo_soci ?? $radicado->terceroEnviado->nombre_completo) : 'Destinatario Externo',
                'asunto' => $radicado->asunto
            ];

            $uploadData = ArchivoHelper::guardarArchivoConMetadatos(
                $request, 
                'archivo_digital', 
                self::DISK, 
                $metadatosInternos, 
                $archivoActual
            );

            if (!$uploadData) {
                return $this->errorResponse('No se pudo procesar el archivo', null, 400);
            }

            $nuevoArchivo = $uploadData['path'];
            $hashSha256 = $uploadData['hash'];
            $mimeType = $uploadData['mime'];
            $fileSize = $uploadData['size'];

            $usuario = Auth::user();
            $radicado->update([
                'archivo_digital' => $nuevoArchivo,
                'hash_sha256' => $hashSha256,
                'archivo_tipo' => $mimeType,
                'archivo_peso' => $fileSize,
                'subido_por' => $usuario?->id,
            ]);

            FileMetadataHelper::crearMetadataArchivoDigitalEnviados($radicado, $nuevoArchivo, $hashSha256, $fileSize);

            try {
                $ocrText = null;
                $ocrHttpService = app(\App\Services\VentanillaUnica\OcrHttpService::class);

                if ($ocrHttpService->isEnabled() && $ocrHttpService->isAvailable()) {
                    $ocrText = $ocrHttpService->extractText($nuevoArchivo, self::DISK);

                    if ($ocrText) {
                        $radicado->update([
                            'ocr' => $ocrText,
                            'ocr_aplicado' => true,
                        ]);

                        \Log::info('OCR HTTP aplicado exitosamente en radicado enviado', [
                            'radicado_id' => $radicado->id,
                            'source' => 'http_service',
                            'texto_length' => strlen($ocrText),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('OCR falló en radicado enviado pero no afecta upload', [
                    'radicado_id' => $radicado->id,
                    'error' => $e->getMessage(),
                ]);
            }

            DB::commit();

            $nombreUsuario = $usuario ? trim($usuario->nombres . ' ' . $usuario->apellidos) : 'No se registró usuario';
            $fileUrl = ArchivoHelper::obtenerUrl($nuevoArchivo, self::DISK);

            return $this->successResponse([
                'path' => $nuevoArchivo,
                'hash_sha256' => $hashSha256,
                'archivo_tipo' => $mimeType,
                'archivo_peso' => $fileSize,
                'uploaded_by' => $nombreUsuario,
                'file_size' => $fileSize,
                'file_type' => $mimeType,
                'file_url' => $fileUrl,
            ], 'Archivo subido exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error en upload archivo digital enviado', [
                'radicado_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->errorResponse('Error al subir el archivo', $e->getMessage(), 500);
        }
    }

    public function download($id)
    {
        try {
            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            if (!ArchivoHelper::obtenerUrl($radicado->archivo_digital, self::DISK)) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            return Storage::disk(self::DISK)->download($radicado->archivo_digital);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar el archivo', $e->getMessage(), 500);
        }
    }

    public function deleteFile($id)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $archivoEliminado = $radicado->archivo_digital;
            ArchivoHelper::eliminarArchivo($archivoEliminado, self::DISK);

            $usuario = Auth::user();
            VentanillaRadicaEnviadosArchivoEliminado::create([
                'radica_enviado_id' => $radicado->id,
                'archivo' => $archivoEliminado,
                'deleted_by' => $usuario?->id,
                'deleted_at' => now(),
            ]);

            $radicado->update(['archivo_digital' => null, 'subido_por' => null]);
            DB::commit();

            return $this->successResponse([
                'deleted_by' => $usuario ? trim($usuario->nombres . ' ' . $usuario->apellidos) : 'Usuario no identificado',
                'deleted_at' => now(),
            ], 'Archivo eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el archivo', $e->getMessage(), 500);
        }
    }

    public function getFileInfo($id)
    {
        try {
            $radicado = VentanillaRadicaEnviados::with('usuarioSubio')->find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            if (!ArchivoHelper::obtenerUrl($radicado->archivo_digital, self::DISK)) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            $fileInfo = [
                'file_name' => basename($radicado->archivo_digital),
                'file_size' => Storage::disk(self::DISK)->size($radicado->archivo_digital),
                'file_type' => Storage::disk(self::DISK)->mimeType($radicado->archivo_digital),
                'uploaded_at' => $radicado->updated_at,
                'uploaded_by' => $radicado->usuarioSubio
                    ? trim($radicado->usuarioSubio->nombres . ' ' . $radicado->usuarioSubio->apellidos)
                    : 'Usuario no identificado',
                'file_url' => ArchivoHelper::obtenerUrl($radicado->archivo_digital, self::DISK),
            ];

            return $this->successResponse($fileInfo, 'Información del archivo obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener información del archivo', $e->getMessage(), 500);
        }
    }

    public function historialEliminaciones($id)
    {
        try {
            $radicado = VentanillaRadicaEnviados::find($id);
            if (!$radicado) {
                return $this->errorResponse('Radicado enviado no encontrado', null, 404);
            }

            $historial = VentanillaRadicaEnviadosArchivoEliminado::where('radica_enviado_id', $id)
                ->with('usuario:id,nombres,apellidos')
                ->orderBy('deleted_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de eliminaciones obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial', $e->getMessage(), 500);
        }
    }

    public function getOcr($id)
    {
        try {
            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('El radicado no tiene archivo digital', null, 400);
            }

            return $this->successResponse([
                'ocr' => $radicado->ocr,
                'ocr_aplicado' => $radicado->ocr_aplicado,
                'archivo' => $radicado->nom_origi ?? basename($radicado->archivo_digital),
            ], 'OCR obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener OCR', $e->getMessage(), 500);
        }
    }

    public function recargarOcr($id)
    {
        try {
            $radicado = VentanillaRadicaEnviados::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('El radicado no tiene archivo digital', null, 400);
            }

            $ocrText = null;
            $ocrHttpService = app(\App\Services\VentanillaUnica\OcrHttpService::class);

            if ($ocrHttpService->isEnabled() && $ocrHttpService->isAvailable()) {
                $ocrText = $ocrHttpService->extractText($radicado->archivo_digital, self::DISK);
            }

            if (!$ocrText) {
                $ocrService = app(\App\Services\VentanillaUnica\OcrService::class);
                if ($ocrService->isAvailable()) {
                    $ocrText = $ocrService->extractText($radicado->archivo_digital, self::DISK);
                }
            }

            if ($ocrText) {
                $radicado->update([
                    'ocr' => $ocrText,
                    'ocr_aplicado' => true,
                ]);

                return $this->successResponse([
                    'ocr' => $ocrText,
                    'ocr_aplicado' => true,
                    'texto_length' => strlen($ocrText),
                ], 'OCR re-aplicado exitosamente');
            }

            return $this->errorResponse('No se pudo extraer texto del documento', null, 500);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al re-aplicar OCR', $e->getMessage(), 500);
        }
    }
}