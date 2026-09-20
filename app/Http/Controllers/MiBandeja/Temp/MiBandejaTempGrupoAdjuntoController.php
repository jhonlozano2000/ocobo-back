<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Helpers\ArchivoHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MiBandeja\Concerns\AutorizaGrupoColaborativo;
use App\Http\Requests\MiBandeja\StoreGrupoAdjuntoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTempGrupoArchiAdjunto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Controlador para gestionar adjuntos de grupos colaborativos temporales.
 * Permite subir, listar, eliminar y descargar archivos adjuntos.
 */
class MiBandejaTempGrupoAdjuntoController extends Controller
{
    use ApiResponseTrait;
    use AutorizaGrupoColaborativo;

    private const DISK = 'mi_bandeja_temp';

    private const PERM = 'Mi Bandeja - Grupos Colaborativos -> ';

    /**
     * Constructor del controlador.
     * Aplica middleware de permisos para subir adjuntos.
     */
    public function __construct()
    {
        $this->middleware('can:'.self::PERM.'Subir Adjuntos')->only(['store', 'destroy']);
    }

    /**
     * Lista los adjuntos de un grupo colaborativo temporal.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @return JsonResponse Respuesta JSON con los adjuntos del grupo
     */
    public function index($grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $adjuntos = $grupo->adjuntos()->with('subidoPor')->orderBy('created_at', 'desc')->get();

            return $this->successResponse($adjuntos, 'Adjuntos del grupo');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener adjuntos', $e->getMessage(), 500);
        }
    }

    /**
     * Sube un nuevo adjunto a un grupo colaborativo temporal.
     *
     * @param  StoreGrupoAdjuntoRequest  $request  Solicitud HTTP con el archivo a subir
     * @param  int  $grupoId  Identificador del grupo
     * @return JsonResponse Respuesta JSON con el adjunto creado
     */
    public function store(StoreGrupoAdjuntoRequest $request, $grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $archivo = $request->file('archivo');
            $tempRequest = new Request;
            $tempRequest->files->set('archivo', $archivo);

            $uploadData = ArchivoHelper::guardarArchivoConHash($tempRequest, 'archivo', self::DISK);

            if (! $uploadData) {
                return $this->errorResponse('Error al subir archivo', null, 500);
            }

            $adjunto = MiBandejaTempGrupoArchiAdjunto::create([
                'grupo_id' => $grupoId,
                'archivo' => $uploadData['path'],
                'nombre_original' => $archivo->getClientOriginalName(),
                'tipo_mime' => $archivo->getMimeType(),
                'peso' => $archivo->getSize(),
                'hash_sha256' => $uploadData['hash'],
                'subido_por' => Auth::id(),
            ]);

            return $this->successResponse($adjunto, 'Adjunto subido exitosamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al subir adjunto', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un adjunto de un grupo colaborativo temporal.
     * También elimina el archivo del disco.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del adjunto a eliminar
     * @return JsonResponse Respuesta JSON con resultado de la operación
     */
    public function destroy($grupoId, $id)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $adjunto = MiBandejaTempGrupoArchiAdjunto::where('grupo_id', $grupoId)->find($id);

            if (! $adjunto) {
                return $this->errorResponse('Adjunto no encontrado', null, 404);
            }

            if (Storage::disk(self::DISK)->exists($adjunto->archivo)) {
                Storage::disk(self::DISK)->delete($adjunto->archivo);
            }

            $adjunto->delete();

            return $this->successResponse(null, 'Adjunto eliminado');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al eliminar adjunto', $e->getMessage(), 500);
        }
    }

    /**
     * Descarga un adjunto de un grupo colaborativo temporal.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del adjunto a descargar
     * @return BinaryFileResponse|Illuminate\Http\JsonResponse Respuesta con el archivo o error
     */
    public function descargar($grupoId, $id)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $adjunto = MiBandejaTempGrupoArchiAdjunto::where('grupo_id', $grupoId)->find($id);

            if (! $adjunto) {
                return $this->errorResponse('Adjunto no encontrado', null, 404);
            }

            if (! Storage::disk(self::DISK)->exists($adjunto->archivo)) {
                return $this->errorResponse('Archivo no encontrado en disco', null, 404);
            }

            return Storage::disk(self::DISK)->download($adjunto->archivo, $adjunto->nombre_original);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al descargar adjunto', $e->getMessage(), 500);
        }
    }
}
