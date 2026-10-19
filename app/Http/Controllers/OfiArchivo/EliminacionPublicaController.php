<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoEliminacionLote;
use App\Services\OfiArchivo\EliminacionDocumentalService;
use App\Services\OfiArchivo\TransferenciaLotesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * B18 (diseño de expedientes §3.8 E): página pública, sin sesión, de las eliminaciones. Publica
 * el inventario de cada lote y, ejecutado, su acta; mientras dura la publicación recibe las
 * observaciones de la ciudadanía. No expone quién observó ni datos internos del lote.
 */
class EliminacionPublicaController extends Controller
{
    use ApiResponseTrait;

    private const CAMPOS = [
        'codigo', 'trd_cod', 'trd_nom', 'dependencia_cod', 'dependencia_nom', 'rusd_numero', 'fecha_publicacion',
        'fin_publicacion', 'acta_comite_numero', 'acta_comite_fecha', 'metodo', 'ejecutado_at', 'codigo_verificacion', 'estado',
    ];

    public function __construct(private EliminacionDocumentalService $eliminacion) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'vigencia' => 'nullable|in:abiertos,ejecutados',
            'search' => 'nullable|string|max:100',
        ]);

        $pagina = $this->publicos()
            ->withCount('items')
            ->when(($filtros['vigencia'] ?? null) === 'abiertos', fn ($q) => $q
                ->where('estado', OfiArchivoEliminacionLote::PUBLICADO)->whereDate('fin_publicacion', '>=', today()))
            ->when(($filtros['vigencia'] ?? null) === 'ejecutados', fn ($q) => $q->where('estado', OfiArchivoEliminacionLote::EJECUTADO))
            ->when($filtros['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('codigo', 'like', "%{$s}%")->orWhere('trd_nom', 'like', "%{$s}%")->orWhere('dependencia_nom', 'like', "%{$s}%")))
            ->orderByDesc('fecha_publicacion')->orderByDesc('id')
            ->paginate(15, [...self::CAMPOS, 'id']);

        $pagina->getCollection()->transform(fn (OfiArchivoEliminacionLote $lote) => $this->publico($lote) + ['items_count' => $lote->items_count]);

        return $this->successResponse($pagina, 'Eliminaciones publicadas');
    }

    public function show(string $codigo): JsonResponse
    {
        $lote = $this->buscar($codigo);

        return $this->successResponse($this->publico($lote) + [
            'items' => $lote->items()->get(['id', 'orden', 'numero_expediente', 'nombre_publico', 'fecha_inicial', 'fecha_final', 'folios', 'soporte']),
            'observaciones_count' => $lote->observaciones()->count(),
            'entidad' => TransferenciaLotesService::entidad(),
        ], 'Eliminación publicada');
    }

    public function inventario(string $codigo): Response
    {
        return OfiArchivoEliminacionLoteController::pdf($this->buscar($codigo, true), 'inventario');
    }

    public function acta(string $codigo): Response
    {
        return OfiArchivoEliminacionLoteController::pdf($this->buscar($codigo, true), 'acta');
    }

    public function observar(Request $request, string $codigo): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => 'required|string|min:3|max:150',
            'identificacion' => 'nullable|string|max:30',
            'correo' => 'nullable|email|max:150',
            'expediente' => 'nullable|string|max:100',
            'observacion' => 'required|string|min:10|max:3000',
            // Campo trampa: el formulario lo oculta, los robots lo llenan
            'sitio_web' => 'prohibited',
        ], ['sitio_web.prohibited' => 'No se pudo registrar la observación.']);

        $observacion = $this->eliminacion->observar($this->buscar($codigo), $datos, $request->ip());

        return $this->createdResponse(['id' => $observacion->id, 'radicada' => $observacion->created_at?->toDateTimeString()],
            'Observación registrada. El comité la estudiará antes de decidir la eliminación.');
    }

    private function publicos()
    {
        return OfiArchivoEliminacionLote::where('estado', '<>', OfiArchivoEliminacionLote::ANULADO);
    }

    private function buscar(string $codigo, bool $conBinarios = false): OfiArchivoEliminacionLote
    {
        return $this->publicos()->where('codigo', $codigo)
            ->firstOrFail($conBinarios ? ['*'] : array_merge(self::CAMPOS, ['id']));
    }

    private function publico(OfiArchivoEliminacionLote $lote): array
    {
        return collect($lote->only(self::CAMPOS))
            ->put('fecha_publicacion', $lote->fecha_publicacion?->toDateString())
            ->put('fin_publicacion', $lote->fin_publicacion?->toDateString())
            ->put('acta_comite_fecha', $lote->acta_comite_fecha?->toDateString())
            ->put('ejecutado_at', $lote->ejecutado_at?->toDateString())
            ->put('abierto_observaciones', $lote->abiertoAObservaciones())
            ->all();
    }
}
