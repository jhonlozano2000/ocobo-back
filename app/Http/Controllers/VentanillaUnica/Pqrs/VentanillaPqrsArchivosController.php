<?php

namespace App\Http\Controllers\VentanillaUnica\Pqrs;

use App\Helpers\ArchivoHelper;
use App\Helpers\FileMetadataHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\Pqrs\UploadPqrsAdjuntosRequest;
use App\Http\Requests\Ventanilla\Pqrs\UploadPqrsDigitalRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrsArchivo;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciArchivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VentanillaPqrsArchivosController extends Controller
{
    use ApiResponseTrait;

    private const DISK = 'pqrs_archivos';

    private const ADJUNTOS_DISK = 'radicados_recibidos';

    private const PERM = 'Radicar -> PQRSF -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Subir digital')->only(['subirDigital']);
        $this->middleware('can:'.self::PERM.'Eliminar digital')->only(['eliminarDigital']);
        $this->middleware('can:'.self::PERM.'Subir adjuntos')->only(['subirAdjuntos']);
        $this->middleware('can:'.self::PERM.'Eliminar adjuntos')->only(['eliminarAdjunto']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['descargarDigital', 'descargarAdjunto', 'listar']);
    }

    public function subirDigital($id, UploadPqrsDigitalRequest $request)
    {
        try {
            DB::beginTransaction();

            $pqrs = VentanillaPqrs::with(['radicado', 'radicado.tercero'])->find($id);
            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $radicado = $pqrs->radicado;
            if (! $radicado) {
                return $this->errorResponse('Radicado asociado no encontrado', null, 404);
            }

            $archivo = $request->file('archivo_digital');
            $archivoActual = $radicado->archivo_digital;

            $metadatosInternos = [
                'titulo' => $radicado->num_radicado,
                'autor' => $radicado->tercero ? $radicado->tercero->nom_razo_soci : 'Remitente Externo',
                'asunto' => $radicado->asunto,
            ];

            $uploadData = ArchivoHelper::guardarArchivoConMetadatos(
                $request,
                'archivo_digital',
                self::DISK,
                $metadatosInternos,
                $archivoActual,
                $radicado->num_radicado
            );

            if (! $uploadData) {
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

            $digitalActualPqrs = $pqrs->archivoDigital;
            if ($digitalActualPqrs) {
                ArchivoHelper::eliminarArchivo($digitalActualPqrs->path, self::DISK);
                $digitalActualPqrs->delete();
            }

            $archivoDigital = VentanillaPqrsArchivo::create([
                'ventanilla_pqrs_id' => $pqrs->id,
                'tipo' => 'digital',
                'nombre_original' => $nombreOriginal,
                'nombre_guardado' => basename($nuevoArchivo),
                'path' => $nuevoArchivo,
                'mime_type' => $mimeType,
                'tamanio' => $fileSize,
                'hash_sha256' => $hashSha256,
                'uploaded_by' => $usuario?->id,
            ]);

            DB::commit();

            $nombreUsuario = $usuario
                ? trim($usuario->nombres.' '.$usuario->apellidos)
                : 'No se registró usuario';

            return $this->successResponse([
                'id' => $archivoDigital->id,
                'nombre' => $archivoDigital->nombre_original,
                'tamanio' => $archivoDigital->tamanio_formateado,
                'tipo' => $archivoDigital->mime_type,
                'hash_sha256' => $archivoDigital->hash_sha256,
                'uploaded_by' => $nombreUsuario,
                'file_url' => ArchivoHelper::obtenerUrl($nuevoArchivo, self::DISK),
            ], 'Archivo digital subido exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al subir archivo digital', $e->getMessage(), 500);
        }
    }

    public function descargarDigital($id)
    {
        try {
            $pqrs = VentanillaPqrs::find($id);
            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $archivo = $pqrs->archivoDigital;
            if (! $archivo) {
                return $this->errorResponse('Archivo digital no encontrado', null, 404);
            }

            if (! Storage::disk(self::DISK)->exists($archivo->path)) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            return Storage::disk(self::DISK)->download($archivo->path, $archivo->nombre_original);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar archivo', $e->getMessage(), 500);
        }
    }

    public function eliminarDigital($id)
    {
        try {
            DB::beginTransaction();

            $pqrs = VentanillaPqrs::find($id);
            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $archivo = $pqrs->archivoDigital;
            if (! $archivo) {
                return $this->errorResponse('Archivo digital no encontrado', null, 404);
            }

            ArchivoHelper::eliminarArchivo($archivo->path, self::DISK);
            $archivo->delete();

            DB::commit();

            return $this->successResponse(null, 'Archivo digital eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al eliminar archivo digital', $e->getMessage(), 500);
        }
    }

    public function subirAdjuntos($id, UploadPqrsAdjuntosRequest $request)
    {
        try {
            DB::beginTransaction();

            $pqrs = VentanillaPqrs::with('radicado')->find($id);
            if (! $pqrs) {
                DB::rollBack();

                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $radicado = $pqrs->radicado;
            if (! $radicado) {
                DB::rollBack();

                return $this->errorResponse('Radicado asociado no encontrado', null, 404);
            }

            $archivos = $request->file('archivos');
            if (! is_array($archivos)) {
                $archivos = $request->hasFile('archivos') ? [$archivos] : [];
            }

            if (empty($archivos)) {
                DB::rollBack();

                return $this->errorResponse('No se encontraron archivos válidos', null, 400);
            }

            $archivosSubidos = [];
            $usuario = Auth::user();

            foreach ($request->file('archivos') as $archivo) {
                $tempRequest = new Request;
                $tempRequest->files->set('archivo', $archivo);

                $uploadData = ArchivoHelper::guardarArchivoConHash($tempRequest, 'archivo', self::ADJUNTOS_DISK);
                if (! $uploadData) {
                    continue;
                }

                $archivoAdicional = VentanillaRadicaReciArchivo::create([
                    'radicado_id' => $radicado->id,
                    'subido_por' => $usuario?->id,
                    'archivo' => $uploadData['path'],
                    'nom_origi' => $archivo->getClientOriginalName(),
                    'archivo_peso' => $archivo->getSize(),
                    'hash_sha256' => $uploadData['hash'],
                ]);

                FileMetadataHelper::crearMetadataArchivoAdjunto($archivoAdicional);

                $archivosSubidos[] = [
                    'id' => $archivoAdicional->id,
                    'nombre' => $archivoAdicional->nom_origi,
                    'ruta' => $uploadData['path'],
                    'hash_sha256' => $uploadData['hash'],
                    'subido_por' => $usuario ? trim($usuario->nombres.' '.$usuario->apellidos) : 'No se registró usuario',
                    'tamaño' => $archivoAdicional->archivo_peso,
                    'tipo' => $archivo->getMimeType(),
                    'file_url' => ArchivoHelper::obtenerUrl($uploadData['path'], self::ADJUNTOS_DISK),
                ];
            }

            DB::commit();

            return $this->successResponse($archivosSubidos, 'Archivos adjuntos subidos exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al subir archivos adjuntos', $e->getMessage(), 500);
        }
    }

    public function descargarAdjunto($id, $archivoId)
    {
        try {
            $pqrs = VentanillaPqrs::find($id);
            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $archivo = VentanillaRadicaReciArchivo::where('radicado_id', $pqrs->ventanilla_radica_reci_id)
                ->where('id', $archivoId)
                ->first();

            if (! $archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $fileUrl = ArchivoHelper::obtenerUrl($archivo->archivo, self::ADJUNTOS_DISK);
            if (! $fileUrl) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            $nombreOriginal = $archivo->nom_origi ?: basename($archivo->archivo);

            return Storage::disk(self::ADJUNTOS_DISK)->download($archivo->archivo, $nombreOriginal);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar archivo', $e->getMessage(), 500);
        }
    }

    public function eliminarAdjunto($id, $archivoId)
    {
        try {
            DB::beginTransaction();

            $pqrs = VentanillaPqrs::find($id);
            if (! $pqrs) {
                DB::rollBack();

                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $archivo = VentanillaRadicaReciArchivo::where('radicado_id', $pqrs->ventanilla_radica_reci_id)
                ->where('id', $archivoId)
                ->first();

            if (! $archivo) {
                DB::rollBack();

                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $archivoPath = $archivo->archivo;
            ArchivoHelper::eliminarArchivo($archivoPath, self::ADJUNTOS_DISK);
            $archivo->delete();

            DB::commit();

            return $this->successResponse(null, 'Archivo adjunto eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al eliminar archivo adjunto', $e->getMessage(), 500);
        }
    }

    public function listar($id)
    {
        try {
            $pqrs = VentanillaPqrs::find($id);
            if (! $pqrs) {
                return $this->errorResponse('PQRS no encontrada', null, 404);
            }

            $radicadoId = $pqrs->ventanilla_radica_reci_id;
            if (! $radicadoId) {
                return $this->successResponse([], 'No hay archivos adjuntos');
            }

            $archivos = VentanillaRadicaReciArchivo::with('usuarioSubido')
                ->where('radicado_id', $radicadoId)
                ->get()
                ->map(function ($archivo) {
                    return [
                        'id' => $archivo->id,
                        'nombre' => $archivo->nom_origi,
                        'ruta' => $archivo->archivo,
                        'url' => ArchivoHelper::obtenerUrl($archivo->archivo, self::ADJUNTOS_DISK),
                        'tamaño' => $archivo->archivo_peso,
                        'hash_sha256' => $archivo->hash_sha256,
                        'subido_por' => $archivo->usuarioSubido
                            ? trim($archivo->usuarioSubido->nombres.' '.$archivo->usuarioSubido->apellidos)
                            : 'No identificado',
                        'fecha_subida' => $archivo->created_at->toIso8601String(),
                        'file_url' => ArchivoHelper::obtenerUrl($archivo->archivo, self::ADJUNTOS_DISK),
                    ];
                });

            return $this->successResponse($archivos, 'Archivos obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener archivos', $e->getMessage(), 500);
        }
    }
}
