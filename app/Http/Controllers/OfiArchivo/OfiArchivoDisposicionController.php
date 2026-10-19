<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoDigitalizacion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoSeleccionLote;
use App\Services\OfiArchivo\DisposicionFinalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * B22 (diseño de expedientes §3.8 S y M/D): expedientes en disposición final, lotes de selección
 * y digitalizaciones. Las reglas están en DisposicionFinalService.
 */
class OfiArchivoDisposicionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private DisposicionFinalService $disposicion) {}

    /** Expedientes en disposición que el usuario puede ver, con lo que les falta. */
    public function expedientes(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'destino' => 'nullable|in:conservar,eliminar,sin_destino',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $pagina = OfiArchivoExpediente::visiblesPara($request->user())
            ->where('estado', 'en_disposicion')
            ->withCount('digitalizaciones')
            ->when($filtros['destino'] ?? null, fn ($q, $destino) => $destino === 'sin_destino'
                ? $q->whereNull('destino_disposicion')
                : $q->where('destino_disposicion', $destino))
            ->when($filtros['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('numero_expediente', 'like', "%{$search}%")
                ->orWhere('nombre_expediente', 'like', "%{$search}%")))
            ->orderBy('fecha_disposicion')->orderBy('id')
            ->paginate($filtros['per_page'] ?? 15);

        $pagina->getCollection()->transform(fn (OfiArchivoExpediente $e) => $this->fila($e));

        return $this->successResponse($pagina, 'Expedientes en disposición final obtenidos');
    }

    /** Series y subseries de selección con expedientes esperando lote. */
    public function pendientesSeleccion(): JsonResponse
    {
        return $this->successResponse($this->disposicion->pendientesDeSeleccion(), 'Series pendientes de selección');
    }

    public function selecciones(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'estado' => 'nullable|in:propuesta,confirmada,anulada',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $pagina = OfiArchivoSeleccionLote::with(['usuario:id,nombres,apellidos', 'confirmadoPor:id,nombres,apellidos'])
            ->withCount(['items', 'items as seleccionados_count' => fn ($q) => $q->where('seleccionado', true)])
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($filtros['per_page'] ?? 15);

        return $this->successResponse($pagina, 'Selecciones obtenidas');
    }

    public function seleccion(int $id): JsonResponse
    {
        return $this->successResponse($this->cargar(OfiArchivoSeleccionLote::findOrFail($id)), 'Selección obtenida');
    }

    public function proponer(Request $request): JsonResponse
    {
        $datos = $request->validate(['trd_id' => 'required|integer']);

        $lote = $this->disposicion->proponer((int) $datos['trd_id'], $request->user());

        return $this->createdResponse($this->cargar($lote), "Propuesta {$lote->codigo} creada");
    }

    public function ajustar(Request $request, int $id, int $item): JsonResponse
    {
        $datos = $request->validate(['seleccionado' => 'required|boolean']);

        $item = $this->disposicion->ajustar(OfiArchivoSeleccionLote::findOrFail($id), $item, (bool) $datos['seleccionado']);

        return $this->successResponse($item, $item->seleccionado ? 'Expediente seleccionado' : 'Expediente quitado de la selección');
    }

    public function confirmar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['justificacion' => 'nullable|string|max:2000']);

        $lote = $this->disposicion->confirmar(OfiArchivoSeleccionLote::findOrFail($id), $datos['justificacion'] ?? null, $request->user());

        return $this->successResponse($this->cargar($lote), "Selección {$lote->codigo} confirmada");
    }

    public function anular(int $id): JsonResponse
    {
        $lote = $this->disposicion->anular(OfiArchivoSeleccionLote::findOrFail($id));

        return $this->successResponse($this->cargar($lote), "Propuesta {$lote->codigo} anulada");
    }

    /** Digitalizaciones del expediente, cada una con su verificación. */
    public function digitalizaciones(Request $request, int $id): JsonResponse
    {
        $expediente = OfiArchivoExpediente::visiblesPara($request->user())->find($id);
        if (! $expediente) {
            return $this->notFoundResponse('Expediente no encontrado');
        }

        $filas = $expediente->digitalizaciones()->with('usuario:id,nombres,apellidos')->get()
            ->map(fn (OfiArchivoDigitalizacion $d) => $d->toArray() + ['verificacion' => $this->disposicion->verificar($d)]);

        return $this->successResponse($filas, 'Digitalizaciones obtenidas');
    }

    public function digitalizar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'archivo' => 'required|file',
            'paginas' => 'nullable|integer|min:1|max:1000000',
            'resolucion_dpi' => 'nullable|integer|min:50|max:4800',
            'observaciones' => 'nullable|string|max:1000',
        ], ['archivo.required' => 'Adjunte la copia digital del expediente.']);

        $expediente = OfiArchivoExpediente::visiblesPara($request->user())->find($id);
        if (! $expediente) {
            return $this->notFoundResponse('Expediente no encontrado');
        }

        $digitalizacion = $this->disposicion->digitalizar($expediente, $request->file('archivo'), $datos, $request->user());

        return $this->createdResponse($digitalizacion->load('usuario:id,nombres,apellidos'), 'Digitalización registrada con su certificado');
    }

    public function certificado(Request $request, int $id): Response
    {
        $digitalizacion = $this->digitalizacionVisible($request, $id);

        return response($digitalizacion->certificado, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificado-digitalizacion-'.$digitalizacion->codigo_verificacion.'.pdf"',
        ]);
    }

    public function archivo(Request $request, int $id): Response
    {
        $digitalizacion = $this->digitalizacionVisible($request, $id);
        if (! Storage::disk(DisposicionFinalService::DISCO)->exists($digitalizacion->archivo_path)) {
            return $this->notFoundResponse('No se encuentra la copia digital');
        }

        return Storage::disk(DisposicionFinalService::DISCO)->download($digitalizacion->archivo_path, $digitalizacion->nombre_original);
    }

    private function digitalizacionVisible(Request $request, int $id): OfiArchivoDigitalizacion
    {
        $digitalizacion = OfiArchivoDigitalizacion::findOrFail($id);
        OfiArchivoExpediente::visiblesPara($request->user())->findOrFail($digitalizacion->expediente_id);

        return $digitalizacion;
    }

    private function cargar(OfiArchivoSeleccionLote $lote): OfiArchivoSeleccionLote
    {
        return $lote->load([
            'usuario:id,nombres,apellidos',
            'confirmadoPor:id,nombres,apellidos',
            'items.expediente:id,numero_expediente,nombre_expediente,fecha_cierre,dependencia_nom,destino_disposicion',
        ]);
    }

    /** @return array<string, mixed> */
    private function fila(OfiArchivoExpediente $expediente): array
    {
        $trd = $expediente->trdRetencion();
        $disposicion = $this->disposicion->disposicion($expediente, $trd);
        $requiere = $this->disposicion->requiereDigitalizacion($expediente, $trd);
        $destino = $this->disposicion->destino($expediente, $trd);

        return [
            'id' => $expediente->id,
            'numero_expediente' => $expediente->numero_expediente,
            'nombre_expediente' => $expediente->nombre_expediente,
            'dependencia' => trim($expediente->dependencia_cod.' '.$expediente->dependencia_nom),
            'serie' => $trd ? trim($trd->cod.' '.$trd->nom) : null,
            'disposicion' => $disposicion,
            'disposicion_final' => $trd?->disposicion_final,
            'destino' => $destino,
            'requiere_digitalizacion' => $requiere,
            'digitalizaciones' => (int) $expediente->digitalizaciones_count,
            'fecha_cierre' => $expediente->fecha_cierre?->toDateString(),
            'fecha_disposicion' => $expediente->fecha_disposicion?->toDateTimeString(),
            'pendiente' => match (true) {
                $destino === null => 'seleccion',
                $requiere && ! $expediente->digitalizaciones_count => 'digitalizacion',
                $destino === DisposicionFinalService::CONSERVAR => 'transferencia',
                default => 'eliminacion',
            },
        ];
    }
}
