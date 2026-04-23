<?php

namespace App\Http\Controllers\VentanillaUnica\Recibidos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\Recibidos\UploadArchivoRecibidoRequest;
use App\Helpers\ArchivoHelper;
use App\Helpers\FileMetadataHelper;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciArchivoEliminado;
use App\Services\VentanillaUnica\OcrService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para la gestión del archivo digital principal de radicaciones recibidas.
 *
 * Maneja exclusivamente el archivo digital principal (campo archivo_digital).
 *
 * @package App\Http\Controllers\VentanillaUnica\Recibidos
 */
class VentanillaRadicaReciDigitalController extends Controller
{
    use ApiResponseTrait;

    private const DISK = 'radicados_recibidos';
    private const PERM = 'Radicar -> Cores. Recibida -> ';

    public function __construct()
    {
        $this->middleware('can:' . self::PERM . 'Subir digital')->only(['upload']);
        $this->middleware('can:' . self::PERM . 'Eliminar digital')->only(['deleteFile']);
        $this->middleware('can:' . self::PERM . 'Mostrar')->only(['download', 'getFileInfo']);
    }

    /**
     * Sube el archivo digital principal.
     */
    public function upload($id, UploadArchivoRecibidoRequest $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaReci::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            $archivo = $request->file('archivo_digital');
            $archivoActual = $radicado->archivo_digital;

            $metadatosInternos = [
                'titulo' => $radicado->num_radicado,
                'autor' => $radicado->tercero ? $radicado->tercero->nom_razo_soci : 'Remitente Externo',
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
            $nombreOriginal = $archivo->getClientOriginalName();

            $usuario = Auth::user();

            $radicado->update([
                'archivo_digital' => $nuevoArchivo,
                'nom_origi' => $nombreOriginal,
                'hash_sha256' => $hashSha256,
                'archivo_tipo' => $mimeType,
                'archivo_peso' => $fileSize,
                'uploaded_by' => $usuario?->id,
            ]);

            $ocrText = null;
            $ocrService = app(\App\Services\VentanillaUnica\OcrService::class);
            $ocrHttpService = app(\App\Services\VentanillaUnica\OcrHttpService::class);

            try {
                if ($ocrHttpService->isAvailable()) {
                    $ocrText = $ocrHttpService->extractText($nuevoArchivo, self::DISK);
                }

                if (!$ocrText && $ocrService->isAvailable()) {
                    $ocrText = $ocrService->extractText($nuevoArchivo, self::DISK);
                }

                if ($ocrText) {
                    $radicado->update([
                        'ocr' => $ocrText,
                        'ocr_aplicado' => true,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('OCR falló pero no afecta upload', [
                    'radicado_id' => $radicado->id,
                    'error' => $e->getMessage()
                ]);
            }

            FileMetadataHelper::crearMetadataArchivoDigital($radicado, $nuevoArchivo, $hashSha256, $fileSize);

            DB::commit();

            $nombreUsuario = $usuario
                ? trim($usuario->nombres . ' ' . $usuario->apellidos)
                : 'No se registró usuario';

            $fileUrl = ArchivoHelper::obtenerUrl($nuevoArchivo, self::DISK);

            return $this->successResponse([
                'path' => $nuevoArchivo,
                'nom_origi' => $nombreOriginal,
                'hash_sha256' => $hashSha256,
                'archivo_tipo' => $mimeType,
                'archivo_peso' => $fileSize,
                'uploaded_by' => $nombreUsuario,
                'file_url' => $fileUrl,
                'ocr_aplicado' => !empty($ocrText),
            ], 'Archivo subido exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al subir el archivo', $e->getMessage(), 500);
        }
    }

    /**
     * Descarga el archivo digital.
     */
    public function download($id)
    {
        try {
            $radicado = VentanillaRadicaReci::find($id);

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

    /**
     * Elimina el archivo digital.
     */
    public function deleteFile($id)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaReci::find($id);

            if (!$radicado || !$radicado->archivo_digital) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $archivoEliminado = $radicado->archivo_digital;
            ArchivoHelper::eliminarArchivo($archivoEliminado, self::DISK);

            $usuario = Auth::user();
            VentanillaRadicaReciArchivoEliminado::create([
                'radica_reci_id' => $radicado->id,
                'archivo' => $archivoEliminado,
                'deleted_by' => $usuario?->id,
                'deleted_at' => now(),
            ]);

            $radicado->update([
                'archivo_digital' => null,
                'nom_origi' => null,
                'uploaded_by' => null
            ]);

            DB::commit();

            return $this->successResponse([
                'deleted_by' => $usuario
                    ? trim($usuario->nombres . ' ' . $usuario->apellidos)
                    : 'Usuario no identificado',
                'deleted_at' => now(),
            ], 'Archivo eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el archivo', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene información del archivo digital.
     */
    public function getFileInfo($id)
    {
        try {
            $radicado = VentanillaRadicaReci::with('usuarioSubio')->find($id);

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

    /**
     * Obtiene el OCR del archivo digital.
     */
    public function getOcr($id)
    {
        try {
            $radicado = VentanillaRadicaReci::find($id);

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

    /**
     * Recarga el OCR del archivo digital.
     */
    public function recargarOcr($id)
    {
        try {
            $radicado = VentanillaRadicaReci::find($id);

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

    /**
     * Historial de eliminaciones de archivos digitales.
     */
    public function historialEliminaciones($id)
    {
        try {
            $radicado = VentanillaRadicaReci::find($id);
            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            $historial = VentanillaRadicaReciArchivoEliminado::where('radica_reci_id', $id)
                ->with('usuario:id,nombres,apellidos')
                ->orderBy('deleted_at', 'desc')
                ->get();

            return $this->successResponse($historial, 'Historial de eliminaciones obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial', $e->getMessage(), 500);
        }
    }
}