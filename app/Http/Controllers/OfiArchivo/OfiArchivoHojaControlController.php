<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteHojaControl;
use App\Models\OfiArchivo\OfiArchivoExpedienteHojaControlVersion;
use App\Models\OfiArchivo\OfiArchivoExpedienteTomo;
use App\Services\OfiArchivo\HojaControlService;
use App\Services\OfiArchivo\TiposDocumentalesExpediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * B19 (diseño de expedientes §3.3): hoja de control y tomos de los expedientes físicos e híbridos.
 * Los documentos se registran con el expediente abierto; la hoja se firma al cerrarlo.
 */
class OfiArchivoHojaControlController extends Controller
{
    use ApiResponseTrait;

    private const UBICACION = [
        'ubicacion_fisica' => 'nullable|string|max:200',
        'ubicacion_id' => 'nullable|integer',
        'caja' => 'nullable|string|max:50',
        'carpeta' => 'nullable|string|max:50',
    ];

    public function __construct(private HojaControlService $hoja) {}

    public function show(int $id, TiposDocumentalesExpediente $tipos): JsonResponse
    {
        $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($id);

        return $this->successResponse($this->estado($expediente) + [
            'tipos_documentales' => HojaControlService::aplica($expediente) ? $tipos->catalogo($expediente) : [],
        ], 'Hoja de control del expediente');
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'tipo_documental_id' => 'required|integer',
            'fecha_documento' => 'nullable|date|before_or_equal:today',
            'folios' => 'required|integer|min:1|max:5000',
            'observaciones' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($datos, $id) {
            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->lockForUpdate()->findOrFail($id);
            $registro = $this->hoja->registrar($expediente, $datos, Auth::user());

            return $this->createdResponse($this->estado($expediente->refresh()),
                "Documento registrado en los folios {$registro->folio_inicial} a {$registro->folio_final}");
        });
    }

    public function anular(Request $request, int $id, int $registroId): JsonResponse
    {
        $request->validate(['motivo' => 'required|string|min:5|max:500']);

        return DB::transaction(function () use ($request, $id, $registroId) {
            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->lockForUpdate()->findOrFail($id);
            $registro = OfiArchivoExpedienteHojaControl::where('expediente_id', $expediente->id)->findOrFail($registroId);
            $this->hoja->anular($expediente, $registro, trim($request->motivo), Auth::user());

            return $this->successResponse($this->estado($expediente->refresh()), 'Registro anulado; sus folios no se renumeran');
        });
    }

    public function abrirTomo(Request $request, int $id): JsonResponse
    {
        $ubicacion = $request->validate(self::UBICACION);

        return DB::transaction(function () use ($ubicacion, $id) {
            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->lockForUpdate()->findOrFail($id);
            $tomo = $this->hoja->abrirTomo($expediente, $ubicacion, Auth::user());

            return $this->createdResponse($this->estado($expediente), "Tomo {$tomo->numero} abierto desde el folio {$tomo->folio_inicial}");
        });
    }

    public function ubicarTomo(Request $request, int $id, int $tomoId): JsonResponse
    {
        $ubicacion = $request->validate(self::UBICACION);

        return DB::transaction(function () use ($ubicacion, $id, $tomoId) {
            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->lockForUpdate()->findOrFail($id);
            if ($expediente->estado === 'eliminado') {
                return $this->warningResponse('El expediente fue eliminado; sus tomos no se reubican.');
            }
            $tomo = OfiArchivoExpedienteTomo::where('expediente_id', $expediente->id)->findOrFail($tomoId);
            $this->hoja->ubicarTomo($tomo, $ubicacion);

            return $this->successResponse($this->estado($expediente), "Ubicación del tomo {$tomo->numero} actualizada");
        });
    }

    /**
     * La versión firmada indicada o la última; con el expediente abierto, el borrador actual.
     */
    public function pdf(Request $request, int $id)
    {
        $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($id);
        abort_unless(HojaControlService::aplica($expediente), 404, 'El expediente electrónico no lleva hoja de control');

        $version = $request->filled('version')
            ? OfiArchivoExpedienteHojaControlVersion::where('expediente_id', $expediente->id)->where('version', (int) $request->version)->firstOrFail()
            : ($expediente->estado === 'abierto' ? null : $this->hoja->ultimaVersion($expediente));

        $contenido = $version?->pdf ?? $this->hoja->pdf($expediente);
        $nombre = 'Hoja_Control_'.$expediente->numero_expediente.($version ? "_v{$version->version}" : '_borrador').'.pdf';

        return response($contenido)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$nombre.'"')
            ->header('X-Hash-Sha256', hash('sha256', $contenido));
    }

    private function estado(OfiArchivoExpediente $expediente): array
    {
        $aplica = HojaControlService::aplica($expediente);
        $versiones = OfiArchivoExpedienteHojaControlVersion::where('expediente_id', $expediente->id)->orderByDesc('version')->get();
        $ultima = $versiones->first();

        return [
            'aplica' => $aplica,
            'editable' => $aplica && $expediente->estado === 'abierto',
            'folios_por_tomo' => HojaControlService::foliosPorTomo(),
            'folios_fisicos' => (int) $expediente->folios_fisicos,
            'tomos' => $this->hoja->tomos($expediente),
            'registros' => $this->hoja->filas($expediente),
            'versiones' => $versiones->map(fn (OfiArchivoExpedienteHojaControlVersion $v) => $v->only([
                'id', 'version', 'motivo', 'total_documentos', 'total_folios', 'total_tomos', 'firmante', 'firmado_en', 'codigo_verificacion',
            ]))->values(),
            'integridad' => $ultima && $expediente->estado !== 'abierto' ? $this->hoja->verificarVersion($ultima, $expediente) : null,
        ];
    }
}
