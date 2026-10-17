<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\StoreTareaArchivoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\WorkFlowArchivo;
use App\Services\Workflows\TareasDeNodoService;
use App\Services\Workflows\WorkFlowArchivoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * M19-35: evidencia de una Tarea. Ver/descargar: quien puede ver la tarea; subir: propietario o
 * responsable mientras la tarea siga abierta; borrar: quien lo subió (o el propietario) con la tarea abierta.
 */
class TareaArchivoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly WorkFlowArchivoService $archivoService
    ) {
        $this->middleware('can:Workflows -> Archivos -> Ver')->only(['index']);
        $this->middleware('can:Workflows -> Archivos -> Subir')->only(['store']);
        $this->middleware('can:Workflows -> Archivos -> Descargar')->only(['download']);
        $this->middleware('can:Workflows -> Archivos -> Eliminar')->only(['destroy']);
    }

    public function index(Tarea $tarea)
    {
        $this->authorize('view', $tarea);

        return $this->successResponse(
            $tarea->archivos()->with('uploader:id,nombres,apellidos')->latest()->get(),
            'Archivos obtenidos correctamente'
        );
    }

    public function store(StoreTareaArchivoRequest $request, Tarea $tarea)
    {
        $this->authorize('completar', $tarea);
        $this->exigirAbierta($tarea);

        $archivo = $this->archivoService->subir(
            $request->file('archivo'),
            $tarea->workflow_id,
            Tarea::class,
            $tarea->id,
            $request->validated('categoria') ?? 'evidencia'
        );

        return $this->successResponse($archivo->load('uploader:id,nombres,apellidos'), 'Archivo subido correctamente', 201);
    }

    public function download(Tarea $tarea, int $archivo)
    {
        $this->authorize('view', $tarea);
        $archivo = $this->archivoDe($tarea, $archivo);

        abort_unless(Storage::disk($archivo->disk)->exists($archivo->ruta_almacenada), 404, 'El archivo no existe en el servidor');

        return Storage::disk($archivo->disk)->download(
            $archivo->ruta_almacenada,
            $archivo->nombre_original,
            ['Content-Type' => $archivo->mime_type]
        );
    }

    public function destroy(Request $request, Tarea $tarea, int $archivo)
    {
        $archivo = $this->archivoDe($tarea, $archivo);
        if ((int) $archivo->uploaded_by !== (int) $request->user()->id) {
            $this->authorize('update', $tarea);
        }
        $this->exigirAbierta($tarea);

        $this->archivoService->eliminar($tarea->workflow_id, $archivo->id);

        return $this->successResponse(null, 'Archivo eliminado correctamente');
    }

    private function archivoDe(Tarea $tarea, int $archivoId): WorkFlowArchivo
    {
        return $tarea->archivos()->findOrFail($archivoId);
    }

    /** La evidencia de una tarea cerrada no se altera (trazabilidad, ISO 9001 7.5.3). */
    private function exigirAbierta(Tarea $tarea): void
    {
        abort_if(
            in_array($tarea->estado, TareasDeNodoService::ESTADOS_CERRADOS, true),
            422,
            "La tarea está {$tarea->estado}: su evidencia ya no se modifica"
        );
    }
}
