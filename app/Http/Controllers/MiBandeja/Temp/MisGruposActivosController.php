<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MisGruposActivosController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            $grupos = MiBandejaTemp::with([
                    'ultimaVersion.bloqueadoPor:id,nombres,apellidos',
                    'responsables.user:id,nombres,apellidos',
                    'firmantes.user:id,nombres,apellidos',
                    'proyectores.user:id,nombres,apellidos',
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

                return [
                    'id' => $grupo->id,
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
                ];
            });

            return $this->successResponse($data, 'Grupos activos del usuario');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener grupos activos', $e->getMessage(), 500);
        }
    }
}
