<?php

namespace App\Http\Controllers\VentanillaUnica\Internos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\Internos\UploadArchivoInternoRequest;
use App\Helpers\ArchivoHelper;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VentanillaRadicaInternoDigitalController extends Controller
{
    use ApiResponseTrait;

    private const DISK = 'radicados_internos';
    private const PERM = 'Radicar -> Cores. Interna -> ';

    public function __construct()
    {
        $this->middleware('can:' . self::PERM . 'Subir digital')->only(['upload']);
        $this->middleware('can:' . self::PERM . 'Eliminar digital')->only(['deleteFile']);
        $this->middleware('can:' . self::PERM . 'Mostrar')->only(['getFileInfo', 'download', 'getOcr']);
    }

    public function upload($id, UploadArchivoInternoRequest $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaInterno::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $archivo = $request->file('archivo_digital');

            if (!$archivo) {
                return $this->errorResponse('No se encontró archivo para subir', null, 400);
            }

            $metadatosInternos = [
                'titulo' => $radicado->num_radicado,
                'asunto' => $radicado->asunto ?? 'Radicado Interno',
            ];

            $uploadData = ArchivoHelper::guardarArchivoConMetadatos(
                $request,
                'archivo_digital',
                self::DISK,
                $metadatosInternos
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
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('OCR falló en radicado interno pero no afecta upload', [
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
            ], 'Archivo digital subido exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al subir el archivo digital', $e->getMessage(), 500);
        }
    }

    public function download($id)
    {
        try {
            $radicado = VentanillaRadicaInterno::find($id);

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
            $radicado = VentanillaRadicaInterno::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $archivoEliminado = $radicado->archivo_digital;
            ArchivoHelper::eliminarArchivo($archivoEliminado, self::DISK);

            $radicado->update(['archivo_digital' => null, 'subido_por' => null]);

            return $this->successResponse(null, 'Archivo eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el archivo', $e->getMessage(), 500);
        }
    }

    public function getFileInfo($id)
    {
        try {
            $radicado = VentanillaRadicaInterno::with('usuarioSubido')->find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $fileInfo = [
                'file_name' => basename($radicado->archivo_digital),
                'file_size' => Storage::disk(self::DISK)->size($radicado->archivo_digital),
                'file_type' => Storage::disk(self::DISK)->mimeType($radicado->archivo_digital),
                'uploaded_at' => $radicado->updated_at,
                'uploaded_by' => $radicado->usuarioSubido
                    ? trim($radicado->usuarioSubido->nombres . ' ' . $radicado->usuarioSubido->apellidos)
                    : 'Usuario no identificado',
                'file_url' => ArchivoHelper::obtenerUrl($radicado->archivo_digital, self::DISK),
            ];

            return $this->successResponse($fileInfo, 'Información del archivo obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener información del archivo', $e->getMessage(), 500);
        }
    }

    public function getOcr($id)
    {
        try {
            $radicado = VentanillaRadicaInterno::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('El radicado no tiene archivo digital', null, 400);
            }

            return $this->successResponse([
                'ocr' => $radicado->ocr,
                'ocr_aplicado' => $radicado->ocr_aplicado,
                'archivo' => basename($radicado->archivo_digital),
            ], 'OCR obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener OCR', $e->getMessage(), 500);
        }
    }

    public function historialEliminaciones($id)
    {
        try {
            $radicado = VentanillaRadicaInterno::find($id);
            if (!$radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $historial = \App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoArchivosEliminados::where('radica_interno_id', $id)
                ->with('usuario:id,nombres,apellidos')
                ->orderBy('deleted_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de eliminaciones obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial', $e->getMessage(), 500);
        }
    }

    public function recargarOcr($id)
    {
        try {
            $radicado = VentanillaRadicaInterno::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('El radicado no tiene archivo digital', null, 400);
            }

            $ocrText = null;
            $ocrHttpService = app(\App\Services\VentanillaUnica\OcrHttpService::class);

            if ($ocrHttpService->isEnabled() && $ocrHttpService->isAvailable()) {
                $ocrText = $ocrHttpService->extractText($radicado->archivo_digital, self::DISK);
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
