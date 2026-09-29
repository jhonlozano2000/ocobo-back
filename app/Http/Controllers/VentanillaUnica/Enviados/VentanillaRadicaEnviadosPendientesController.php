<?php

namespace App\Http\Controllers\VentanillaUnica\Enviados;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VentanillaRadicaEnviadosPendientesController extends Controller
{
    use ApiResponseTrait;

    private const PERM_LISTAR = 'Radicar -> Cores. Enviada -> Listar';

    private const PERM_CREAR = 'Radicar -> Cores. Enviada -> Crear';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM_LISTAR)->only(['index', 'show']);
    }

    /**
     * Listar grupos colaborativos con flujo terminado pendientes por radicar como enviados.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = MiBandejaTemp::query()
                ->where('plantilla_cargada', true)
                ->where(function ($q) {
                    $q->whereNull('respuesta_final')
                        ->orWhere('respuesta_final', '!=', 'radicado');
                })
                // No debe tener radicado enviado ya asignado
                ->where(function ($q) {
                    $q->where('radicado_tipo', '!=', 'enviado')
                        ->orWhereNull('radicado_tipo')
                        ->orWhereNull('radicado_id');
                })
                // Todos los proyectores deben haber cumplido su tarea si existen
                ->whereDoesntHave('proyectores', function ($q) {
                    $q->where('estado_tarea', '!=', 'cumplido');
                })
                // Todos los revisores deben haber cumplido su tarea si existen
                ->whereDoesntHave('revisores', function ($q) {
                    $q->where('estado_tarea', '!=', 'cumplido');
                })
                // Todos los firmantes deben haber cumplido y firmado si existen
                ->whereDoesntHave('firmantes', function ($q) {
                    $q->where(function ($qf) {
                        $qf->where('estado_tarea', '!=', 'cumplido')
                            ->orWhereNull('fechor_firmado');
                    });
                })
                // Todos los aprobadores deben haber cumplido si existen
                ->whereDoesntHave('aprobadores', function ($q) {
                    $q->where('estado_tarea', '!=', 'cumplido');
                })
                // Debe tener al menos un miembro asignado en alguna de las 4 categorías
                ->where(function ($q) {
                    $q->has('proyectores')
                        ->orHas('revisores')
                        ->orHas('firmantes')
                        ->orHas('aprobadores');
                });

            // Filtro de búsqueda
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('asunto', 'like', "%{$search}%")
                        ->orWhereHas('radicadoRecibido', function ($qr) use ($search) {
                            $qr->where('num_radicado', 'like', "%{$search}%");
                        });
                });
            }

            if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
                $query->whereBetween('updated_at', [$request->fecha_desde, $request->fecha_hasta]);
            }

            $query->with([
                'radicadoRecibido.tercero',
                'radicadoRecibido.clasificacionDocumental',
                'plantilla',
                'ultimaVersion.subidoPor',
                'proyectores.user',
                'proyectores.cargo',
                'revisores.user',
                'revisores.cargo',
                'firmantes.user',
                'firmantes.cargo',
                'aprobadores.user',
                'aprobadores.cargo',
                'creadoPor',
            ])->orderBy('updated_at', 'desc');

            $perPage = (int) $request->get('per_page', 10);
            $pendientes = $query->paginate($perPage);

            return $this->successResponse($pendientes, 'Listado de pendientes por radicar obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al consultar pendientes por radicar', $e->getMessage(), 500);
        }
    }

    /**
     * Obtener detalle del grupo colaborativo para precargar el drawer de radicación enviada.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $grupo = MiBandejaTemp::with([
                'radicadoRecibido.tercero',
                'radicadoRecibido.clasificacionDocumental',
                'plantilla',
                'ultimaVersion.subidoPor',
                'proyectores.user.cargoActivo.cargo',
                'proyectores.cargo',
                'revisores.user.cargoActivo.cargo',
                'revisores.cargo',
                'firmantes.user.cargoActivo.cargo',
                'firmantes.cargo',
                'aprobadores.user.cargoActivo.cargo',
                'aprobadores.cargo',
                'creadoPor',
            ])->find($id);

            if (! $grupo) {
                return $this->errorResponse('Grupo colaborativo no encontrado', null, 404);
            }

            $radicadoRecibido = $grupo->radicadoRecibido;
            $tercero = $radicadoRecibido?->tercero;
            $clasificacion = $radicadoRecibido?->clasificacionDocumental;
            $ultimaVersion = $grupo->ultimaVersion;

            $resolveMember = function ($member) {
                $user = $member->user;
                $userCargo = $user?->cargoActivo;
                $cargo = $member->cargo ?: $userCargo?->cargo;

                $dependencia = null;
                if ($cargo) {
                    $jerarquia = method_exists($cargo, 'getJerarquiaCompleta') ? $cargo->getJerarquiaCompleta() : [];
                    foreach ($jerarquia as $item) {
                        if (($item['tipo'] ?? '') === 'Dependencia') {
                            $dependencia = $item;
                        }
                    }
                }

                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'users_cargos_id' => $userCargo?->id,
                    'cargo_id' => $userCargo?->id,
                    'usuario' => $user ? [
                        'id' => $user->id,
                        'nombres' => $user->nombres,
                        'apellidos' => $user->apellidos,
                        'full_name' => trim($user->nombres.' '.$user->apellidos),
                    ] : null,
                    'cargo' => $cargo ? [
                        'id' => $cargo->id,
                        'nombre' => $cargo->nom_organico,
                        'codigo' => $cargo->cod_organico,
                    ] : null,
                    'dependencia' => $dependencia,
                    'dependencia_id' => $dependencia['id'] ?? null,
                    'estado_tarea' => $member->estado_tarea,
                    'fechor_firmado' => $member->fechor_firmado ?? null,
                ];
            };

            // Mapeo estructurado para precarga en FormRadicadoEnviadoDrawer
            $data = [
                'grupo_id' => $grupo->id,
                'nombre_grupo' => $grupo->nombre,
                'asunto' => $grupo->asunto ?: ($radicadoRecibido?->asunto ?? ''),
                'radicado_origen_id' => $radicadoRecibido?->id,
                'radicado_origen_numero' => $radicadoRecibido?->num_radicado,
                'tercero_id' => $tercero?->id,
                'tercero' => $tercero ? [
                    'id' => $tercero->id,
                    'num_docu_nit' => $tercero->num_docu_nit,
                    'nom_razo_soci' => $tercero->nom_razo_soci,
                    'direccion' => $tercero->direccion,
                    'telefono' => $tercero->telefono,
                    'email' => $tercero->email,
                    'tipo' => $tercero->tipo,
                    'divi_poli_id' => $tercero->divi_poli_id,
                ] : null,
                'clasifica_documen_id' => $clasificacion?->id,
                'clasificacion_documental' => $clasificacion ? [
                    'id' => $clasificacion->id,
                    'nom' => $clasificacion->nom,
                    'codigo_completo' => method_exists($clasificacion, 'getCodigoCompleto') ? $clasificacion->getCodigoCompleto() : $clasificacion->cod,
                    'nombre_completo' => method_exists($clasificacion, 'getNombreCompleto') ? $clasificacion->getNombreCompleto() : $clasificacion->nom,
                ] : null,
                'documento_version' => $ultimaVersion ? [
                    'id' => $ultimaVersion->id,
                    'version' => $ultimaVersion->version,
                    'nombre_original' => $ultimaVersion->nombre_original,
                    'nombre_archivo' => $ultimaVersion->nombre_archivo,
                    'ruta_completa' => $ultimaVersion->ruta_completa,
                    'peso' => $ultimaVersion->peso,
                    'extension' => $ultimaVersion->extension,
                    'mime_type' => $ultimaVersion->mime_type,
                    'hash_seguridad' => $ultimaVersion->hash_seguridad,
                ] : null,
                'proyectores' => $grupo->proyectores->map($resolveMember),
                'revisores' => $grupo->revisores->map($resolveMember),
                'firmantes' => $grupo->firmantes->map($resolveMember),
            ];

            return $this->successResponse($data, 'Detalle de pendiente para radicar obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener detalle del pendiente', $e->getMessage(), 500);
        }
    }
}
