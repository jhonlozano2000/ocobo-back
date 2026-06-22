<?php

namespace App\Http\Controllers\VentanillaUnica\Recibidos;

use App\Helpers\ArchivoHelper;
use App\Helpers\FileMetadataHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\Recibidos\UploadArchivosAdjuntosRecibidoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciArchivo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controlador para la gestión de archivos adjuntos de radicaciones recibidas.
 *
 * Maneja los archivos adicionales (tabla ventanilla_radica_reci_archivos).
 */
class VentanillaRadicaReciAdjuntosController extends Controller
{
    use ApiResponseTrait;

    private const DISK = 'radicados_recibidos';

    private const PERM = 'Radicar -> Cores. Recibida -> ';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Subir adjuntos')->only(['subir', 'subirArchivosAdjuntos']);
        $this->middleware('can:'.self::PERM.'Eliminar adjuntos')->only(['eliminar', 'eliminarArchivoAdjunto']);
        $this->middleware('can:'.self::PERM.'Mostrar')->only(['listar', 'listarArchivosAdjuntos', 'descargar', 'descargarArchivoAdjunto']);
    }

    /**
     * Sube archivos adjuntos.
     */
    public function subir($id, UploadArchivosAdjuntosRecibidoRequest $request)
    {
        try {
            $radicado = VentanillaRadicaReci::find($id);
            if (! $radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            $archivos = $request->file('archivos');
            if (empty($archivos)) {
                return $this->errorResponse('No se encontraron archivos válidos', null, 400);
            }

            $archivosSubidos = [];
            $usuario = Auth::user();
            $storage = Storage::disk(self::DISK);

            foreach ($archivos as $archivo) {
                if (! $archivo || ! $archivo->isValid()) {
                    continue;
                }

                // Guardar archivo directamente en el disco
                $nombreArchivo = Str::random(50).'.'.$archivo->getClientOriginalExtension();
                $contenido = $archivo->getContent();
                if (empty($contenido)) {
                    continue;
                }

                $storage->put($nombreArchivo, $contenido);
                $hash = hash('sha256', $contenido);

                $archivoAdicional = VentanillaRadicaReciArchivo::create([
                    'radicado_id' => $radicado->id,
                    'subido_por' => $usuario?->id,
                    'archivo' => $nombreArchivo,
                    'nom_origi' => $archivo->getClientOriginalName(),
                    'archivo_peso' => $archivo->getSize(),
                    'hash_sha256' => $hash,
                ]);

                FileMetadataHelper::crearMetadataArchivoAdjunto($archivoAdicional);

                $archivosSubidos[] = [
                    'id' => $archivoAdicional->id,
                    'nombre' => $archivoAdicional->nom_origi,
                    'ruta' => $nombreArchivo,
                    'hash_sha256' => $hash,
                    'subido_por' => $usuario ? trim($usuario->nombres.' '.$usuario->apellidos) : 'No se registró usuario',
                    'tamaño' => $archivoAdicional->archivo_peso,
                    'tipo' => $archivo->getMimeType(),
                    'file_url' => ArchivoHelper::obtenerUrl($nombreArchivo, self::DISK),
                ];
            }

            if (empty($archivosSubidos)) {
                return $this->errorResponse('No se pudieron procesar los archivos enviados', null, 422);
            }

            return $this->successResponse($archivosSubidos, 'Archivos adicionales subidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al subir archivos', $e->getMessage(), 500);
        }
    }

    /**
     * Lista archivos adjuntos.
     */
    public function listar($id)
    {
        try {
            $radicado = VentanillaRadicaReci::with('archivos')->find($id);
            if (! $radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            $archivos = $radicado->archivos->map(function ($archivo) {
                return [
                    'id' => $archivo->id,
                    'nombre' => basename($archivo->archivo),
                    'ruta' => $archivo->archivo,
                    'url' => ArchivoHelper::obtenerUrl($archivo->archivo, self::DISK),
                    'tamaño' => \Storage::disk(self::DISK)->size($archivo->archivo),
                    'tipo' => \Storage::disk(self::DISK)->mimeType($archivo->archivo),
                    'fecha_subida' => $archivo->created_at,
                ];
            });

            return $this->successResponse($archivos, 'Archivos obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener archivos', $e->getMessage(), 500);
        }
    }

    /**
     * Descarga un archivo adjunto.
     */
    public function descargar($id, $archivoId)
    {
        try {
            $archivo = VentanillaRadicaReciArchivo::where('radicado_id', $id)
                ->where('id', $archivoId)
                ->first();

            if (! $archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $fileUrl = ArchivoHelper::obtenerUrl($archivo->archivo, self::DISK);
            if (! $fileUrl) {
                return $this->errorResponse('El archivo no existe en el servidor', null, 404);
            }

            $nombreOriginal = $archivo->nom_origi ?: basename($archivo->archivo);

            return \Storage::disk(self::DISK)->download($archivo->archivo, $nombreOriginal);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un archivo adjunto.
     */
    public function eliminar($id, $archivoId)
    {
        try {
            DB::beginTransaction();

            $archivo = VentanillaRadicaReciArchivo::where('radicado_id', $id)
                ->where('id', $archivoId)
                ->first();

            if (! $archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            $archivoPath = $archivo->archivo;
            ArchivoHelper::eliminarArchivo($archivoPath, self::DISK);

            $archivo->delete();
            DB::commit();

            return $this->successResponse(null, 'Archivo eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al eliminar archivo', $e->getMessage(), 500);
        }
    }

    // ── Alias para compatibilidad con las rutas ───────────────────────
    public function subirArchivosAdjuntos($id, UploadArchivosAdjuntosRecibidoRequest $request)
    {
        return $this->subir($id, $request);
    }

    public function listarArchivosAdjuntos($id)
    {
        return $this->listar($id);
    }

    public function descargarArchivoAdjunto($id, $archivoId)
    {
        return $this->descargar($id, $archivoId);
    }

    public function eliminarArchivoAdjunto($id, $archivoId)
    {
        return $this->eliminar($id, $archivoId);
    }
}
