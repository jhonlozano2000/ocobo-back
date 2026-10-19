<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoTransferenciaLote;
use App\Models\User;
use App\Services\OfiArchivo\TransferenciaLotesService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * B17: lotes de transferencia documental (diseño de expedientes §3.7).
 *
 * Quien envía arma el lote con expedientes de una dependencia y lo envía con su FUID; quien
 * recibe coteja cada expediente y lo recibe (acta de entrega) o lo devuelve con observaciones.
 */
class OfiArchivoTransferenciaLoteController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private TransferenciaLotesService $lotes) {}

    /**
     * Listado de lotes visibles para el usuario.
     */
    public function index(Request $request)
    {
        $request->validate([
            'estado' => 'nullable|in:'.implode(',', OfiArchivoTransferenciaLote::ESTADOS),
            'tipo' => 'nullable|in:primaria,secundaria',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $this->visibles(Auth::user())
            ->with(['usuarioEnvia:id,nombres,apellidos', 'usuarioRecibe:id,nombres,apellidos'])
            ->withCount('items')
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s
                ->where('codigo', 'like', "%{$request->search}%")
                ->orWhere('dependencia_nom', 'like', "%{$request->search}%")));

        return $this->successResponse($query->latest('id')->paginate((int) $request->get('per_page', 15)), 'Listado de lotes de transferencia');
    }

    /**
     * Lote con sus expedientes, el cotejo y la verificación de sus firmas.
     */
    public function show($id)
    {
        $lote = $this->visibles(Auth::user())->with([
            'items:id,lote_id,expediente_id,estado,cotejo,observacion_cotejo',
            'items.expediente:id,numero_expediente,nombre_expediente,soporte,folios_fisicos,caja,carpeta,ubicacion_fisica,estado,fase',
            'usuarioEnvia:id,nombres,apellidos',
            'usuarioRecibe:id,nombres,apellidos',
        ])->findOrFail($id);

        return $this->successResponse($lote->toArray() + ['integridad' => $this->lotes->verificar($lote)], 'Lote de transferencia');
    }

    /**
     * Expedientes de la dependencia que podrían ir en un lote, con el motivo de los que no pueden.
     */
    public function candidatos(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:primaria,secundaria',
            'dependencia_id' => 'required|integer',
        ]);

        if (! OfiArchivoExpediente::puedeUsarDependencia(Auth::user(), $request->dependencia_id)) {
            return $this->warningResponse('No tiene acceso a los expedientes de esa dependencia');
        }

        $expedientes = OfiArchivoExpediente::visiblesPara(Auth::user())
            ->with(['serieTrd:id,cod,nom,a_g,a_c,ct,e,s', 'subserieTrd:id,cod,nom,a_g,a_c,ct,e,s'])
            ->where('dependencia_id', $request->dependencia_id)
            ->whereIn('estado', $request->tipo === 'primaria' ? ['cerrado'] : ['cerrado', 'en_disposicion'])
            ->where('fase', $request->tipo === 'primaria' ? 'gestion' : 'central')
            ->orderBy('fecha_cierre')->limit(500)
            ->get(['id', 'numero_expediente', 'nombre_expediente', 'serie_trd_id', 'subserie_trd_id', 'soporte', 'estado', 'fase',
                'fecha_cierre', 'trd_snapshot', 'ubicacion_fisica', 'caja', 'carpeta', 'folios_fisicos', 'dependencia_id']);

        return $this->successResponse($expedientes->map(fn (OfiArchivoExpediente $e) => [
            'id' => $e->id,
            'numero_expediente' => $e->numero_expediente,
            'nombre_expediente' => $e->nombre_expediente,
            'serie' => ($e->subserieTrd ?? $e->serieTrd)?->nom,
            'soporte' => $e->soporte,
            'fecha_cierre' => $e->fecha_cierre?->toDateString(),
            'motivo' => $this->lotes->validarExpediente($e, $request->tipo),
        ])->values(), 'Expedientes candidatos');
    }

    /**
     * Crea el lote y lo envía con su FUID firmado por quien envía.
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'tipo' => 'required|in:primaria,secundaria',
            'dependencia_id' => 'required|integer|exists:calidad_organigrama,id',
            'origen' => 'required|string|max:200',
            'destino' => 'required|string|max:200',
            'expediente_ids' => 'required|array|min:1|max:500',
            'expediente_ids.*' => 'integer|distinct|exists:ofi_archivo_expedientes,id',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        if (! OfiArchivoExpediente::puedeUsarDependencia(Auth::user(), $datos['dependencia_id'])) {
            return $this->warningResponse('No tiene acceso a los expedientes de esa dependencia');
        }

        $lote = $this->lotes->enviar($datos, Auth::user());

        return $this->createdResponse($lote->loadCount('items'), "Lote {$lote->codigo} enviado");
    }

    /**
     * Cotejo de la recepción: recibe el lote si todo es conforme o lo devuelve con observaciones.
     */
    public function recibir(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|distinct',
            'items.*.conforme' => 'required|boolean',
            'items.*.observacion' => 'nullable|string|max:1000',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        $lote = $this->visibles(Auth::user())->findOrFail($id);
        $cotejo = collect($request->items)->mapWithKeys(fn ($item) => [(int) $item['id'] => [
            'conforme' => filter_var($item['conforme'], FILTER_VALIDATE_BOOLEAN),
            'observacion' => $item['observacion'] ?? null,
        ]])->all();

        $lote = $this->lotes->recibir($lote, $cotejo, $request->observaciones, Auth::user());

        return $this->successResponse($lote->loadCount('items'), $lote->estado === 'recibido'
            ? "Lote {$lote->codigo} recibido; el acta de entrega está disponible"
            : "Lote {$lote->codigo} devuelto con observaciones");
    }

    public function fuid($id)
    {
        $lote = $this->visibles(Auth::user())->findOrFail($id);

        return response($lote->fuid)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="FUID_'.$lote->codigo.'.xlsx"')
            ->header('X-Hash-Sha256', $lote->hash_fuid);
    }

    public function acta($id)
    {
        $lote = $this->visibles(Auth::user())->findOrFail($id);
        abort_unless($lote->acta, 404, 'El lote no tiene acta de entrega');

        return response($lote->acta)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="Acta_Transferencia_'.$lote->codigo.'.pdf"')
            ->header('X-Codigo-Verificacion', $lote->codigo_verificacion);
    }

    /**
     * Sin "Ver Todos", los lotes de las dependencias del usuario o enviados por él.
     */
    private function visibles(User $user): Builder
    {
        $query = OfiArchivoTransferenciaLote::query();
        if (OfiArchivoExpediente::puedeVerTodos($user)) {
            return $query;
        }

        $dependencias = OfiArchivoExpediente::dependenciasDelUsuario($user);

        return $query->where(fn ($q) => $q->where('usuario_envia_id', $user->id)
            ->when($dependencias !== [], fn ($q) => $q->orWhereIn('dependencia_id', $dependencias)));
    }
}
