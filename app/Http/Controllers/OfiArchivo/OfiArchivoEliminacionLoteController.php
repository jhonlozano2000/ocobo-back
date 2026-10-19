<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoEliminacionLote;
use App\Models\User;
use App\Services\OfiArchivo\EliminacionDocumentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * B18 (diseño de expedientes §3.8 E): lotes de eliminación documental, desde la propuesta hasta
 * el acta. Las reglas están en EliminacionDocumentalService.
 */
class OfiArchivoEliminacionLoteController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private EliminacionDocumentalService $eliminacion) {}

    /** Series con expedientes listos para eliminar y lo que impide proponerlas. */
    public function pendientes(): JsonResponse
    {
        return $this->successResponse($this->eliminacion->pendientes(), 'Series listas para eliminar');
    }

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in([...OfiArchivoEliminacionLote::ACTIVOS, OfiArchivoEliminacionLote::SUSPENDIDO, OfiArchivoEliminacionLote::ANULADO])],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $pagina = OfiArchivoEliminacionLote::with('usuario:id,nombres,apellidos')
            ->withCount(['items', 'observaciones'])
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($filtros['per_page'] ?? 15);

        return $this->successResponse($pagina, 'Lotes de eliminación obtenidos');
    }

    public function show(int $id): JsonResponse
    {
        return $this->successResponse($this->cargar(OfiArchivoEliminacionLote::findOrFail($id)), 'Lote de eliminación obtenido');
    }

    public function proponer(Request $request): JsonResponse
    {
        $datos = $request->validate(['trd_id' => 'required|integer']);

        $lote = $this->eliminacion->proponer((int) $datos['trd_id'], $request->user());

        return $this->createdResponse($this->cargar($lote), "Lote {$lote->codigo} publicado hasta el {$lote->fin_publicacion->format('Y-m-d')}");
    }

    public function concepto(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'decision' => ['required', Rule::in(OfiArchivoEliminacionLote::DECISIONES)],
            'concepto' => 'required|string|min:10|max:5000',
        ]);

        $lote = $this->eliminacion->emitirConcepto(OfiArchivoEliminacionLote::findOrFail($id), $datos['decision'], $datos['concepto'], $request->user());

        return $this->successResponse($this->cargar($lote), $datos['decision'] === 'continuar'
            ? 'Concepto registrado: la eliminación sigue a aprobación del comité'
            : 'Concepto registrado: el lote queda suspendido y sus expedientes vuelven a disposición');
    }

    public function comite(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'acta_comite_numero' => 'required|string|max:50',
            'acta_comite_fecha' => 'required|date|before_or_equal:today',
            'metodo' => ['required', Rule::in(OfiArchivoEliminacionLote::METODOS)],
            'presidente_id' => 'required|integer|exists:users,id',
            'secretario_id' => 'required|integer|exists:users,id',
        ]);

        $lote = $this->eliminacion->registrarComite(OfiArchivoEliminacionLote::findOrFail($id), $datos, $request->user());

        return $this->successResponse($this->cargar($lote), 'Aprobación del comité registrada: falta la firma del presidente y del secretario técnico');
    }

    /** Actas que esperan la firma del usuario como presidente o secretario técnico. */
    public function porFirmar(Request $request): JsonResponse
    {
        $id = $request->user()->id;
        $lotes = OfiArchivoEliminacionLote::where('estado', OfiArchivoEliminacionLote::APROBADO)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('presidente_id', $id)->whereNull('firma_presidente'))
                ->orWhere(fn ($q) => $q->where('secretario_id', $id)->whereNull('firma_secretario')))
            ->withCount('items')->orderBy('comite_at')->get()
            ->map(fn (OfiArchivoEliminacionLote $lote) => $lote->toArray() + ['rol' => (int) $lote->presidente_id === $id ? 'presidente' : 'secretario']);

        return $this->successResponse($lotes, 'Actas por firmar');
    }

    public function firmar(Request $request, int $id): JsonResponse
    {
        $lote = $this->eliminacion->firmar(OfiArchivoEliminacionLote::findOrFail($id), $request->user());

        return $this->successResponse($this->cargar($lote), $lote->estado === OfiArchivoEliminacionLote::POR_EJECUTAR
            ? 'Acta firmada: la eliminación queda lista para ejecutar'
            : 'Firma registrada: falta la otra firma');
    }

    public function ejecutar(Request $request, int $id): JsonResponse
    {
        $lote = $this->eliminacion->ejecutar(OfiArchivoEliminacionLote::findOrFail($id), $request->user());

        return $this->successResponse($this->cargar($lote), "Eliminación {$lote->codigo} ejecutada; el acta queda publicada");
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['motivo' => 'required|string|min:10|max:1000']);

        $lote = $this->eliminacion->anular(OfiArchivoEliminacionLote::findOrFail($id), $datos['motivo']);

        return $this->successResponse($this->cargar($lote), "Lote {$lote->codigo} anulado");
    }

    public function inventario(int $id): Response
    {
        return self::pdf(OfiArchivoEliminacionLote::findOrFail($id), 'inventario');
    }

    public function acta(int $id): Response
    {
        return self::pdf(OfiArchivoEliminacionLote::findOrFail($id), 'acta');
    }

    /** Usuarios activos que el comité puede designar como presidente o secretario técnico. */
    public function firmantes(Request $request): JsonResponse
    {
        $datos = $request->validate(['search' => 'nullable|string|max:100']);

        $usuarios = User::where('estado', 1)
            ->when($datos['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('nombres', 'like', "%{$s}%")->orWhere('apellidos', 'like', "%{$s}%")))
            ->orderBy('nombres')->orderBy('apellidos')->limit(50)
            ->get(['id', 'nombres', 'apellidos']);

        return $this->successResponse($usuarios, 'Firmantes disponibles');
    }

    /** PDF del inventario o del acta; null si el lote aún no lo tiene. */
    public static function pdf(OfiArchivoEliminacionLote $lote, string $cual): Response
    {
        $contenido = $lote->getRawOriginal($cual);
        if (! $contenido) {
            abort(404, $cual === 'acta' ? 'La eliminación aún no tiene acta' : 'El lote no tiene inventario');
        }

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$cual.'-eliminacion-'.$lote->codigo.'.pdf"',
        ]);
    }

    private function cargar(OfiArchivoEliminacionLote $lote): array
    {
        $lote->load([
            'usuario:id,nombres,apellidos', 'conceptoPor:id,nombres,apellidos', 'presidente:id,nombres,apellidos',
            'secretario:id,nombres,apellidos', 'comitePor:id,nombres,apellidos', 'ejecutadoPor:id,nombres,apellidos',
            'items', 'observaciones.item:id,numero_expediente',
        ]);

        return $lote->toArray() + [
            'firmado_presidente' => (bool) $lote->firma_presidente,
            'firmado_secretario' => (bool) $lote->firma_secretario,
            'abierto_observaciones' => $lote->abiertoAObservaciones(),
        ];
    }
}
