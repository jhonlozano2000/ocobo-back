<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfiArchivo\VincularTerceroExpedienteRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteTercero;
use App\Services\OfiArchivo\ExpedienteEventosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * B24 (diseño de expedientes §2.1): terceros relacionados con el expediente. El vínculo solo
 * cambia mientras el expediente está abierto y cada cambio queda en el historial.
 */
class OfiArchivoExpedienteTerceroController extends Controller
{
    use ApiResponseTrait;

    private const SOLO_ABIERTO = 'Solo se pueden cambiar los terceros de un expediente abierto.';

    public function __construct(private ExpedienteEventosService $eventos)
    {
        $this->middleware('auth:sanctum');
    }

    public function index(int $id): JsonResponse
    {
        $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($id);

        return $this->successResponse($this->vinculos($expediente->id), 'Terceros del expediente');
    }

    public function store(VincularTerceroExpedienteRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->lockForUpdate()->findOrFail($id);
            if ($expediente->estado !== 'abierto') {
                return $this->warningResponse(self::SOLO_ABIERTO);
            }

            $vinculo = OfiArchivoExpedienteTercero::firstOrCreate(
                ['expediente_id' => $expediente->id, 'tercero_id' => $request->tercero_id, 'rol' => $request->rol],
                ['usuario_id' => Auth::id()]
            );
            if ($vinculo->wasRecentlyCreated) {
                $this->eventos->registrar($expediente->id, 'vinculo_tercero', $this->detalle($vinculo));
            }

            return $this->successResponse($this->vinculos($expediente->id), 'Tercero vinculado al expediente');
        });
    }

    public function destroy(int $id, int $vinculoId): JsonResponse
    {
        return DB::transaction(function () use ($id, $vinculoId) {
            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->lockForUpdate()->findOrFail($id);
            $vinculo = OfiArchivoExpedienteTercero::where('expediente_id', $expediente->id)->findOrFail($vinculoId);
            if ($expediente->estado !== 'abierto') {
                return $this->warningResponse(self::SOLO_ABIERTO);
            }

            $detalle = $this->detalle($vinculo);
            $vinculo->delete();
            $this->eventos->registrar($expediente->id, 'desvinculo_tercero', $detalle);

            return $this->successResponse($this->vinculos($expediente->id), 'Tercero desvinculado del expediente');
        });
    }

    private function vinculos(int $expedienteId)
    {
        return OfiArchivoExpedienteTercero::with('tercero:id,num_docu_nit,nom_razo_soci,tipo')
            ->where('expediente_id', $expedienteId)
            ->orderBy('id')
            ->get(['id', 'expediente_id', 'tercero_id', 'rol', 'created_at']);
    }

    private function detalle(OfiArchivoExpedienteTercero $vinculo): array
    {
        $tercero = $vinculo->tercero()->first(['id', 'num_docu_nit', 'nom_razo_soci']);

        return [
            'tercero_id' => $vinculo->tercero_id,
            'tercero' => trim(($tercero->num_docu_nit ?? '').' - '.($tercero->nom_razo_soci ?? ''), ' -'),
            'rol' => $vinculo->rol,
        ];
    }
}
