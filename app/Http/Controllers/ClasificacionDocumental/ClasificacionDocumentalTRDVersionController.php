<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\ClasificacionDocumental\AprobarTRDVersionRequest;
use App\Http\Requests\ClasificacionDocumental\ClasificacionDocumentalTRDVersionRequest;
use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRDVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClasificacionDocumentalTRDVersionController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene el listado de versiones TRD con paginación.
     *
     * Este método retorna todas las versiones de TRD ordenadas por fecha de creación,
     * incluyendo información de la dependencia y el usuario que aprobó.
     *
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las versiones
     *
     * @queryParam per_page integer Número de elementos por página (por defecto: 10). Example: 15
     * @queryParam dependencia_id integer Filtrar por dependencia específica. Example: 1
     * @queryParam estado_version string Filtrar por estado (TEMP, ACTIVO, HISTORICO). Example: "ACTIVO"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Versiones obtenidas exitosamente",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "version": 1,
     *         "estado_version": "ACTIVO",
     *         "dependencia": {
     *           "id": 1,
     *           "nom_organico": "Secretaría General"
     *         },
     *         "aprobado_por": {
     *           "id": 1,
     *           "name": "Admin User"
     *         }
     *       }
     *     ]
     *   }
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = ClasificacionDocumentalTRDVersion::with(['dependencia', 'aprobadoPor']);

            // Aplicar filtros
            if ($request->filled('dependencia_id')) {
                $query->where('dependencia_id', $request->dependencia_id);
            }

            if ($request->filled('estado_version')) {
                $query->where('estado_version', $request->estado_version);
            }

            $perPage = $request->get('per_page', 10);
            $versiones = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->successResponse($versiones, 'Versiones obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las versiones', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva versión TRD para una dependencia.
     *
     * Este método crea una nueva versión temporal que debe ser aprobada
     * antes de ser activada en el sistema.
     *
     * @param ClasificacionDocumentalTRDVersionRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la versión creada
     *
     * @bodyParam dependencia_id integer required ID de la dependencia. Example: 1
     * @bodyParam observaciones string Observaciones sobre la nueva versión. Example: "Actualización de procedimientos"
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Versión creada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "version": 2,
     *     "estado_version": "TEMP",
     *     "dependencia_id": 1
     *   }
     * }
     *
     * @response 400 {
     *   "status": false,
     *   "message": "La dependencia ya tiene una versión pendiente por aprobar"
     * }
     */
    public function store(ClasificacionDocumentalTRDVersionRequest $request)
    {
        try {
            DB::beginTransaction();

            // Verificar si ya existe una versión pendiente
            $versionPendiente = ClasificacionDocumentalTRDVersion::where('dependencia_id', $request->dependencia_id)
                ->where('estado_version', 'TEMP')
                ->exists();

            if ($versionPendiente) {
                return $this->errorResponse('La dependencia ya tiene una versión pendiente por aprobar', null, 400);
            }

            // Obtener la última versión para calcular el número de la nueva
            $ultimaVersion = ClasificacionDocumentalTRDVersion::where('dependencia_id', $request->dependencia_id)
                ->max('version');

            $nuevaVersion = ClasificacionDocumentalTRDVersion::create([
                'dependencia_id' => $request->dependencia_id,
                'version' => ($ultimaVersion ?? 0) + 1,
                'estado_version' => 'TEMP',
                'observaciones' => $request->observaciones,
                'user_register' => auth()->id(),
            ]);

            DB::commit();

            return $this->successResponse($nuevaVersion->load('dependencia'), 'Versión creada exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear la versión', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una versión TRD específica con sus relaciones.
     *
     * @param int $id ID de la versión
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la versión
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Versión obtenida exitosamente",
     *   "data": {
     *     "id": 1,
     *     "version": 1,
     *     "estado_version": "ACTIVO",
     *     "dependencia": {
     *       "id": 1,
     *       "nom_organico": "Secretaría General"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Versión no encontrada"
     * }
     */
    public function show($id)
    {
        try {
            $version = ClasificacionDocumentalTRDVersion::with(['dependencia', 'aprobadoPor'])
                ->find($id);

            if (!$version) {
                return $this->errorResponse('Versión no encontrada', null, 404);
            }

            return $this->successResponse($version, 'Versión obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la versión', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ClasificacionDocumentalTRDVersion $clasificacionDocumentalTRDVersion)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClasificacionDocumentalTRDVersion $clasificacionDocumentalTRDVersion)
    {
        //
    }

    /**
     * Aprueba una versión TRD pendiente.
     *
     * Este método cambia el estado de una versión de TEMP a ACTIVO,
     * marcando las versiones anteriores como HISTORICO.
     *
     * @param AprobarTRDVersionRequest $request La solicitud HTTP validada
     * @param int $dependenciaId ID de la dependencia
     * @return \Illuminate\Http\JsonResponse Respuesta JSON
     *
     * @bodyParam observaciones string required Observaciones sobre la aprobación. Example: "Aprobado por cumplir estándares"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Versión aprobada exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "La dependencia no existe"
     * }
     *
     * @response 400 {
     *   "status": false,
     *   "message": "No hay versiones pendientes por aprobar"
     * }
     */
    public function aprobarVersion(AprobarTRDVersionRequest $request, $dependenciaId)
    {
        try {
            DB::beginTransaction();

            // Verificar que la dependencia existe
            $dependencia = CalidadOrganigrama::find($dependenciaId);
            if (!$dependencia) {
                return $this->errorResponse('La dependencia no existe', null, 404);
            }

            // Buscar versión pendiente
            $versionPendiente = ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)
                ->where('estado_version', 'TEMP')
                ->first();

            if (!$versionPendiente) {
                return $this->errorResponse('No hay versiones pendientes por aprobar', null, 400);
            }

            // Marcar versiones anteriores como HISTORICO
            ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)
                ->where('estado_version', 'ACTIVO')
                ->update(['estado_version' => 'HISTORICO']);

            // Aprobar la nueva versión
            $versionPendiente->update([
                'estado_version' => 'ACTIVO',
                'aprobado_por' => auth()->id(),
                'observaciones' => $request->observaciones,
                'fecha_aprobacion' => now(),
            ]);

            DB::commit();

            return $this->successResponse(null, 'Versión aprobada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al aprobar la versión', $e->getMessage(), 500);
        }
    }

    /**
     * Lista las dependencias con TRD pendientes por aprobar.
     *
     * Este método retorna todas las dependencias que tienen versiones TRD
     * en estado TEMP que requieren aprobación.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con dependencias pendientes
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Dependencias pendientes obtenidas exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "nom_organico": "Secretaría General",
     *       "cod_organico": "SG001",
     *       "trds": [
     *         {
     *           "version": 2,
     *           "estado_version": "TEMP"
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "No hay TRD pendientes por aprobar"
     * }
     */
    public function listarPendientesPorAprobar()
    {
        try {
            // Obtener las dependencias con TRD en estado TEMP
            $dependencias = CalidadOrganigrama::whereHas('trdVersiones', function ($query) {
                $query->where('estado_version', 'TEMP');
            })
                ->with(['trdVersiones' => function ($query) {
                    $query->where('estado_version', 'TEMP')
                        ->select('dependencia_id', 'version', 'estado_version', 'created_at')
                        ->orderBy('version', 'desc');
                }])
                ->select('id', 'nom_organico', 'cod_organico')
                ->get();

            if ($dependencias->isEmpty()) {
                return $this->errorResponse('No hay TRD pendientes por aprobar', null, 404);
            }

            return $this->successResponse($dependencias, 'Dependencias pendientes obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener dependencias pendientes', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas de versiones por dependencia.
     *
     * @param int $dependenciaId ID de la dependencia
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "total_versiones": 5,
     *     "versiones_activas": 1,
     *     "versiones_historicas": 3,
     *     "versiones_pendientes": 1
     *   }
     * }
     */
    public function estadisticas($dependenciaId)
    {
        try {
            $estadisticas = ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)
                ->selectRaw('estado_version, COUNT(*) as total')
                ->groupBy('estado_version')
                ->pluck('total', 'estado_version')
                ->toArray();

            $data = [
                'total_versiones' => array_sum($estadisticas),
                'versiones_activas' => $estadisticas['ACTIVO'] ?? 0,
                'versiones_historicas' => $estadisticas['HISTORICO'] ?? 0,
                'versiones_pendientes' => $estadisticas['TEMP'] ?? 0,
            ];

            return $this->successResponse($data, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }
}
