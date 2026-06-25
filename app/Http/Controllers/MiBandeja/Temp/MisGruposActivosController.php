<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MisGruposActivosController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        try {
            $user = Auth::user();

            $grupos = MiBandejaTemp::with([
                    'ultimaVersion.bloqueadoPor:id,nombres,apellidos',
                    'responsables.user:id,nombres,apellidos',
                    'firmantes.user:id,nombres,apellidos',
                    'proyectores.user:id,nombres,apellidos',
                    'radicadoRecibido.tercero',
                    'radicadoEnviado.tercero',
                    'radicadoInterno',
                ])
                ->where('estado_grupo', 'activo')
                ->where(function ($q) use ($user) {
                    $q->whereHas('responsables', fn($q) => $q->where('user_id', $user->id))
                      ->orWhereHas('firmantes', fn($q) => $q->where('user_id', $user->id))
                      ->orWhereHas('proyectores', fn($q) => $q->where('user_id', $user->id));
                })
                ->orderBy('updated_at', 'desc')
                ->get();

            $data = $grupos->map(function ($grupo) use ($user) {
                $miembroR = $grupo->responsables->firstWhere('user_id', $user->id);
                $miembroF = $grupo->firmantes->firstWhere('user_id', $user->id);
                $miembroP = $grupo->proyectores->firstWhere('user_id', $user->id);

                if ($miembroR) {
                    $miRol = 'responsable';
                    $esCustodio = (bool) $miembroR->es_custodio;
                    $ordenFirma = null;
                    $miEstadoTarea = $miembroR->estado_tarea ?? 'pendiente';
                } elseif ($miembroF) {
                    $miRol = 'firmante';
                    $esCustodio = false;
                    $ordenFirma = $miembroF->orden_firma;
                    $miEstadoTarea = $miembroF->estado_tarea ?? 'pendiente';
                } else {
                    $miRol = 'proyector';
                    $esCustodio = false;
                    $ordenFirma = null;
                    $miEstadoTarea = $miembroP->estado_tarea ?? 'pendiente';
                }

                $ultimaVersion = $grupo->ultimaVersion;
                $versionData = null;
                if ($ultimaVersion) {
                    $bloqueadoPor = $ultimaVersion->bloqueado_por_user_id
                        ? ($ultimaVersion->bloqueadoPor ? [
                            'id' => $ultimaVersion->bloqueadoPor->id,
                            'nombres' => $ultimaVersion->bloqueadoPor->nombres,
                            'apellidos' => $ultimaVersion->bloqueadoPor->apellidos,
                        ] : null)
                        : null;

                    $versionData = [
                        'id' => $ultimaVersion->id,
                        'version' => $ultimaVersion->version,
                        'nombre_original' => $ultimaVersion->nombre_original,
                        'extension' => $ultimaVersion->extension,
                        'esta_disponible' => $ultimaVersion->estaDisponible(),
                        'bloqueado_por' => $bloqueadoPor,
                        'fecha_bloqueo' => $ultimaVersion->fecha_bloqueo?->toISOString(),
                    ];
                }

                // Radicado info via accessor
                $radicadoModel = $grupo->radicado;
                $radicado = null;
                if ($radicadoModel) {
                    $tercero = $radicadoModel->tercero ?? null;
                    $radicado = [
                        'numero' => $radicadoModel->num_radicado ?? null,
                        'fecha_radicado' => $radicadoModel->created_at?->toISOString(),
                        'fecha_vencimiento' => $radicadoModel->fec_venci ? Carbon::parse($radicadoModel->fec_venci)->format('Y-m-d') : null,
                        'tercero' => $tercero?->nom_razo_soci,
                    ];
                }

                // Full member lists
                $responsables = $grupo->responsables->map(fn($r) => [
                    'user' => [
                        'nombres' => $r->user?->nombres,
                        'apellidos' => $r->user?->apellidos,
                    ],
                    'estado_tarea' => $r->estado_tarea ?? 'pendiente',
                    'descargo_plantilla' => (bool) $r->descargo_plantilla,
                    'es_custodio' => (bool) $r->es_custodio,
                ])->values();

                $firmantes = $grupo->firmantes->map(fn($f) => [
                    'user' => [
                        'nombres' => $f->user?->nombres,
                        'apellidos' => $f->user?->apellidos,
                    ],
                    'estado_tarea' => $f->estado_tarea ?? 'pendiente',
                    'descargo_plantilla' => (bool) $f->descargo_plantilla,
                    'orden_firma' => $f->orden_firma,
                ])->values();

                $proyectores = $grupo->proyectores->map(fn($p) => [
                    'user' => [
                        'nombres' => $p->user?->nombres,
                        'apellidos' => $p->user?->apellidos,
                    ],
                    'estado_tarea' => $p->estado_tarea ?? 'pendiente',
                    'descargo_plantilla' => (bool) $p->descargo_plantilla,
                ])->values();

                return [
                    'id' => $grupo->id,
                    'usua_crea_id' => $grupo->usua_crea_id,
                    'nombre' => $grupo->nombre,
                    'asunto' => $grupo->asunto,
                    'estado' => $grupo->estado,
                    'mi_rol' => $miRol,
                    'es_custodio' => $esCustodio,
                    'orden_firma' => $ordenFirma,
                    'mi_estado_tarea' => $miEstadoTarea,
                    'todos_cumplidos' => $grupo->todosTerminados(),
                    'plantilla_cargada' => (bool) $grupo->plantilla_cargada,
                    'plantilla_id' => $grupo->plantilla_id,
                    'ultima_version' => $versionData,
                    'radicado' => $radicado,
                    'responsables' => $responsables,
                    'firmantes' => $firmantes,
                    'proyectores' => $proyectores,
                ];
            });

            return $this->successResponse($data, 'Grupos activos del usuario');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener grupos activos', $e->getMessage(), 500);
        }
    }

    public function liberarBloqueo(Request $request, $id)
    {
        try {
            $user = $request->user();
            $grupo = MiBandejaTemp::find($id);

            if (!$grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $service = app(\App\Services\MiBandeja\GrupoColaborativoService::class);
            $service->liberarBloqueo($grupo, $user);

            return $this->successResponse(null, 'Documento liberado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al liberar documento', $e->getMessage(), 500);
        }
    }
}
