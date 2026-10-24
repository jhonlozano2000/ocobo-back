<?php

namespace App\Http\Controllers\VentanillaUnica\Enviados;

use App\Helpers\ArchivoHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempArchivoVersion;
use App\Models\MiBandeja\MiBandejaTempGrupoArchiAdjunto;
use App\Services\MiBandeja\TempDocumentosRecibidos\DocumentoExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VentanillaRadicaEnviadosPendientesController extends Controller
{
    use ApiResponseTrait;

    private const DISK = 'plantillas_grupos';

    private const PERM_LISTAR = 'Radicar -> Cores. Enviada -> Listar';

    private const PERM_CREAR = 'Radicar -> Cores. Enviada -> Crear';

    public function __construct()
    {
        $this->middleware('can:'.self::PERM_LISTAR)->only(['index', 'show', 'descargarDocumento', 'descargarAdjunto']);
    }

    /**
     * Listar grupos colaborativos con flujo terminado pendientes por radicar como enviados.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = MiBandejaTemp::query()
                // Plantilla Word, o documento del editor (se radica su PDF con la constancia de firmas)
                ->where(function ($q) {
                    $q->where('plantilla_cargada', true)->orWhereNotNull('documento_id');
                })
                // El del editor, solo si se puede radicar (mismas reglas que RadicacionGrupoDocumentoService::pendiente)
                ->where(function ($q) {
                    $q->whereNull('documento_id')->orWhere(function ($d) {
                        $d->where('estado_grupo', 'activo')->where('estado', '!=', 'finalizado')->has('firmantes');
                    });
                })
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
                            $qr->where('num_radicado', 'like', "%{$search}%")
                                ->orWhereHas('tercero', function ($qt) use ($search) {
                                    $qt->where('nom_razo_soci', 'like', "%{$search}%")
                                        ->orWhere('num_docu_nit', 'like', "%{$search}%");
                                });
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
                'versiones.subidoPor',
                'adjuntos.subidoPor',
                'notas.user',
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

            // Mapeo estructurado para precarga en FormRadicadoEnviadoDrawer y vista de detalle completa
            $data = [
                'grupo_id' => $grupo->id,
                'nombre_grupo' => $grupo->nombre,
                'asunto' => $grupo->asunto ?: ($radicadoRecibido?->asunto ?? ''),
                'estado' => $grupo->estado,
                'estado_grupo' => $grupo->estado_grupo,
                'created_at' => $grupo->created_at,
                'updated_at' => $grupo->updated_at,
                'creado_por' => $grupo->creadoPor ? [
                    'id' => $grupo->creadoPor->id,
                    'nombres' => $grupo->creadoPor->nombres,
                    'apellidos' => $grupo->creadoPor->apellidos,
                    'full_name' => trim($grupo->creadoPor->nombres.' '.$grupo->creadoPor->apellidos),
                ] : null,
                'radicado_origen_id' => $radicadoRecibido?->id,
                'radicado_origen_numero' => $radicadoRecibido?->num_radicado,
                'radicado_origen' => $radicadoRecibido ? [
                    'id' => $radicadoRecibido->id,
                    'num_radicado' => $radicadoRecibido->num_radicado,
                    'asunto' => $radicadoRecibido->asunto,
                    'created_at' => $radicadoRecibido->created_at,
                    'fec_venci' => $radicadoRecibido->fec_venci,
                ] : null,
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
                // Documento del editor: se radica su PDF con la constancia de firmas
                'documento_editor' => $grupo->documento_id ? [
                    'id' => $grupo->documento_id,
                    'titulo' => $grupo->documento?->titulo,
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
                    'created_at' => $ultimaVersion->created_at,
                    'subido_por' => $ultimaVersion->subidoPor ? trim($ultimaVersion->subidoPor->nombres.' '.$ultimaVersion->subidoPor->apellidos) : null,
                ] : null,
                'versiones' => $grupo->versiones->map(fn ($v) => [
                    'id' => $v->id,
                    'version' => $v->version,
                    'nombre_original' => $v->nombre_original,
                    'peso' => $v->peso,
                    'extension' => $v->extension,
                    'comentario_version' => $v->comentario_version,
                    'created_at' => $v->created_at,
                    'subido_por' => $v->subidoPor ? trim($v->subidoPor->nombres.' '.$v->subidoPor->apellidos) : null,
                ]),
                'adjuntos' => $grupo->adjuntos->map(fn ($a) => [
                    'id' => $a->id,
                    'archivo' => $a->archivo,
                    'nombre_original' => $a->nombre_original,
                    'tipo_mime' => $a->tipo_mime,
                    'peso' => $a->peso,
                    'hash_sha256' => $a->hash_sha256,
                    'created_at' => $a->created_at,
                    'subido_por' => $a->subidoPor ? trim($a->subidoPor->nombres.' '.$a->subidoPor->apellidos) : null,
                ]),
                'notas' => $grupo->notas->map(fn ($n) => [
                    'id' => $n->id,
                    'contenido' => $n->contenido,
                    'created_at' => $n->created_at,
                    'usuario' => $n->user ? [
                        'id' => $n->user->id,
                        'full_name' => trim($n->user->nombres.' '.$n->user->apellidos),
                    ] : null,
                ]),
                'proyectores' => $grupo->proyectores->map($resolveMember),
                'revisores' => $grupo->revisores->map($resolveMember),
                'firmantes' => $grupo->firmantes->map($resolveMember),
            ];

            return $this->successResponse($data, 'Detalle de pendiente para radicar obtenido exitosamente');
        } catch (\Exception $e) {
            \Log::error('Error al obtener detalle del pendiente para radicar: '.$e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Error al obtener detalle del pendiente', $e->getMessage(), 500);
        }
    }

    /**
     * Descargar documento/versión del grupo colaborativo pendiente.
     */
    public function descargarDocumento(int $id, ?int $versionId = null): BinaryFileResponse|JsonResponse
    {
        try {
            $grupo = MiBandejaTemp::find($id);
            if ($grupo?->documento_id && ! $versionId) {
                $ruta = app(DocumentoExportService::class)->exportarPdf($grupo->documento, true);

                return response()->download($ruta, ($grupo->documento->titulo ?: 'Documento').'.pdf')->deleteFileAfterSend(true);
            }

            $query = MiBandejaTempArchivoVersion::where('grupo_id', $id);

            if ($versionId) {
                $version = $query->find($versionId);
            } else {
                $version = $query->latest('id')->first();
            }

            if (! $version) {
                return $this->errorResponse('Archivo del documento no encontrado', null, 404);
            }

            if (! ArchivoHelper::existe($version->ruta_completa, self::DISK)) {
                return $this->errorResponse('Archivo no encontrado en disco', null, 404);
            }

            return ArchivoHelper::descargar($version->ruta_completa, self::DISK, $version->nombre_original);
        } catch (\Exception $e) {
            \Log::error('Error al descargar documento de pendiente: '.$e->getMessage(), ['id' => $id, 'versionId' => $versionId]);

            return $this->errorResponse('Error al descargar documento', $e->getMessage(), 500);
        }
    }

    /**
     * Descargar archivo adjunto del grupo colaborativo pendiente.
     */
    public function descargarAdjunto(int $id, int $adjuntoId): BinaryFileResponse|JsonResponse
    {
        try {
            $adjunto = MiBandejaTempGrupoArchiAdjunto::where('grupo_id', $id)->find($adjuntoId);

            if (! $adjunto) {
                return $this->errorResponse('Adjunto no encontrado', null, 404);
            }

            if (! ArchivoHelper::existe($adjunto->archivo, self::DISK)) {
                return $this->errorResponse('Archivo adjunto no encontrado en disco', null, 404);
            }

            return ArchivoHelper::descargar($adjunto->archivo, self::DISK, $adjunto->nombre_original);
        } catch (\Exception $e) {
            \Log::error('Error al descargar adjunto de pendiente: '.$e->getMessage(), ['id' => $id, 'adjuntoId' => $adjuntoId]);

            return $this->errorResponse('Error al descargar adjunto', $e->getMessage(), 500);
        }
    }
}
