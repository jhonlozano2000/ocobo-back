<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoAprobador;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use App\Models\MiBandeja\MiBandejaTempGrupoProyector;
use App\Models\MiBandeja\MiBandejaTempGrupoRevisor;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\MiBandeja\TempDocumentosRecibidos\DocumentoUsuario;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\VentanillaUnica\RadicadoEstadoTrabajoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Grupo colaborativo de un documento de Mis Documentos: el mismo formulario de Grupos colaborativos (asunto,
 * descripción, radicado vinculado y proyectores, revisores, aprobadores y firmantes), pero el documento base es el
 * del editor en línea. El grupo queda en `mi_bandeja_temp` (aparece en Grupos colaborativos y en Mis grupos
 * activos) y sus miembros reciben acceso al documento con el rol equivalente.
 */
class GrupoDocumentoController extends Controller
{
    use ApiResponseTrait;

    /** Rol del grupo → tabla de miembros, rol en el documento y tipo de notificación. */
    private const ROLES = [
        'proyectores' => [MiBandejaTempGrupoProyector::class, 'proyector', 'asignacion_proyector', 'proyector'],
        'revisores' => [MiBandejaTempGrupoRevisor::class, 'responsable', 'asignacion_responsable', 'revisor'],
        'aprobadores' => [MiBandejaTempGrupoAprobador::class, 'responsable', 'asignacion_responsable', 'aprobador'],
        'firmantes' => [MiBandejaTempGrupoFirmante::class, 'firmante', 'asignacion_firmante', 'firmante'],
    ];

    /** Si alguien tiene varios roles en el grupo, en el documento queda con el de más alcance. */
    private const PRIORIDAD_ROL_DOCUMENTO = ['proyector' => 1, 'responsable' => 2, 'firmante' => 3];

    private const TABLAS_RADICADO = [
        'recibido' => 'ventanilla_radica_reci',
        'enviado' => 'ventanilla_radica_enviados',
        'interno' => 'ventanilla_radica_internos',
    ];

    /**
     * El grupo vigente del documento (o null) y si el usuario puede crearlo.
     */
    public function show(Request $request, Documento $documento): JsonResponse
    {
        return $this->successResponse([
            'puede_gestionar' => $request->user()->can('gestionarUsuarios', $documento),
            'grupo' => ($grupo = $this->grupoVigente($documento)) ? $this->resumen($grupo) : null,
        ]);
    }

    /**
     * Crea el grupo colaborativo del documento con sus miembros.
     *
     * @bodyParam asunto string required
     * @bodyParam descripcion string
     * @bodyParam radicado_id int Radicado vinculado (opcional)
     * @bodyParam radicado_tipo string recibido, enviado o interno
     * @bodyParam revisores int[] required Al menos uno
     * @bodyParam firmantes int[] required Al menos uno, en orden de firma
     * @bodyParam proyectores int[]
     * @bodyParam aprobadores int[]
     */
    public function store(Request $request, Documento $documento): JsonResponse
    {
        $tablaRadicado = self::TABLAS_RADICADO[$request->input('radicado_tipo')] ?? null;
        $usuarioActivo = Rule::exists('users', 'id')->where('estado', 1);

        $datos = $request->validate([
            'asunto' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'radicado_tipo' => ['nullable', 'required_with:radicado_id', Rule::in(array_keys(self::TABLAS_RADICADO))],
            'radicado_id' => ['nullable', 'integer', ...($tablaRadicado ? [Rule::exists($tablaRadicado, 'id')] : [])],
            'proyectores' => ['nullable', 'array', 'max:20'],
            'revisores' => ['required', 'array', 'min:1', 'max:20'],
            'aprobadores' => ['nullable', 'array', 'max:20'],
            'firmantes' => ['required', 'array', 'min:1', 'max:20'],
            'proyectores.*' => ['integer', 'distinct', $usuarioActivo],
            'revisores.*' => ['integer', 'distinct', $usuarioActivo],
            'aprobadores.*' => ['integer', 'distinct', $usuarioActivo],
            'firmantes.*' => ['integer', 'distinct', $usuarioActivo],
        ], [
            'asunto.required' => 'El asunto es obligatorio.',
            'revisores.required' => 'Agregue al menos un revisor.',
            'firmantes.required' => 'Agregue al menos un firmante.',
            'radicado_id.exists' => 'El radicado seleccionado no existe.',
            '*.*.exists' => 'El funcionario no existe o está inactivo.',
        ]);

        if ($this->grupoVigente($documento)) {
            return $this->errorResponse('El documento ya tiene un grupo colaborativo activo', null, 422);
        }

        $grupo = DB::transaction(function () use ($documento, $datos, $request) {
            $grupo = new MiBandejaTemp([
                'nombre' => mb_substr($documento->titulo ?: $datos['asunto'], 0, 255),
                'asunto' => $datos['asunto'],
                'descripcion' => $datos['descripcion'] ?? null,
                'radicado_id' => $datos['radicado_id'] ?? null,
                'radicado_tipo' => isset($datos['radicado_id']) ? $datos['radicado_tipo'] : null,
                'estado' => 'borrador',
                'estado_grupo' => 'activo',
                'usua_crea_id' => $request->user()->id,
            ]);
            $grupo->documento_id = $documento->id;
            $grupo->save();

            $radicado = $grupo->radicado;
            if ($radicado && $radicado->fec_venci) {
                $radicado->updateQuietly(['estado_trabajo' => RadicadoEstadoTrabajoService::ESTADO_EN_PROCESO]);
            }

            $this->agregarMiembros($grupo, $documento, $datos);

            return $grupo;
        });

        return $this->successResponse($this->resumen($grupo), 'Grupo colaborativo creado', 201);
    }

    private function agregarMiembros(MiBandejaTemp $grupo, Documento $documento, array $datos): void
    {
        $cargos = User::with('cargo')
            ->whereIn('id', collect(array_keys(self::ROLES))->flatMap(fn ($rol) => $datos[$rol] ?? [])->unique())
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->cargo?->id]);
        $rolesDocumento = [];

        foreach (self::ROLES as $rol => [$modelo, $rolDocumento, $tipoNotificacion, $etiqueta]) {
            foreach (array_values($datos[$rol] ?? []) as $indice => $userId) {
                $modelo::create([
                    'grupo_id' => $grupo->id,
                    'user_id' => $userId,
                    'cargo_id' => $cargos[$userId] ?? null,
                    ...($rol === 'firmantes' ? ['orden_firma' => $indice + 1] : []),
                ]);

                Notificacion::create([
                    'user_id' => $userId,
                    'type' => $tipoNotificacion,
                    'title' => 'Asignado en grupo colaborativo',
                    'message' => "Se le ha asignado como {$etiqueta} del grupo \"{$grupo->nombre}\".",
                    'notifiable_type' => MiBandejaTemp::class,
                    'notifiable_id' => $grupo->id,
                    'data' => [
                        'grupo_id' => $grupo->id,
                        'nombre_grupo' => $grupo->nombre,
                        'documento_id' => $documento->id,
                        'asignado_por' => $grupo->usua_crea_id,
                    ],
                ]);

                $actual = $rolesDocumento[$userId] ?? null;
                if (! $actual || self::PRIORIDAD_ROL_DOCUMENTO[$rolDocumento] > self::PRIORIDAD_ROL_DOCUMENTO[$actual]) {
                    $rolesDocumento[$userId] = $rolDocumento;
                }
            }
        }

        // Los miembros editan el documento en línea; el creador ya es su dueño
        foreach ($rolesDocumento as $userId => $rolDocumento) {
            if ((int) $userId === (int) $documento->user_id) {
                continue;
            }
            DocumentoUsuario::updateOrCreate(
                ['documento_id' => $documento->id, 'user_id' => $userId],
                ['rol' => $rolDocumento]
            );
        }
    }

    private function grupoVigente(Documento $documento): ?MiBandejaTemp
    {
        return MiBandejaTemp::where('documento_id', $documento->id)
            ->where('estado_grupo', 'activo')
            ->latest('id')
            ->first();
    }

    private function resumen(MiBandejaTemp $grupo): array
    {
        $grupo->load(array_map(fn ($rol) => "{$rol}.user:id,nombres,apellidos", array_keys(self::ROLES)));
        $radicado = $grupo->radicado;

        return [
            'id' => $grupo->id,
            'nombre' => $grupo->nombre,
            'asunto' => $grupo->asunto,
            'descripcion' => $grupo->descripcion,
            'estado' => $grupo->estado,
            'estado_grupo' => $grupo->estado_grupo,
            'radicado' => $radicado ? [
                'id' => $radicado->id,
                'tipo' => $grupo->radicado_tipo,
                'numero' => $radicado->num_radicado ?? null,
            ] : null,
            'miembros' => collect(array_keys(self::ROLES))->mapWithKeys(fn ($rol) => [
                $rol => $grupo->{$rol}
                    ->sortBy($rol === 'firmantes' ? 'orden_firma' : 'id')
                    ->map(fn ($m) => [
                        'user_id' => $m->user_id,
                        'nombre' => $m->user ? trim("{$m->user->nombres} {$m->user->apellidos}") : null,
                        'estado_tarea' => $m->estado_tarea ?? 'pendiente',
                    ])
                    ->values(),
            ]),
        ];
    }
}
