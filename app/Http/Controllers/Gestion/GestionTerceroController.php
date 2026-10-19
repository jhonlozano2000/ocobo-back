<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\GestionTerceroRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Gestion\GestionTercero;
use App\Services\Gestion\ConsultaIntegralTerceroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class GestionTerceroController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todos los terceros del sistema.
     *
     * Este método retorna todos los terceros con su relación de división política.
     * Incluye opciones de filtrado, búsqueda y paginación.
     *
     * @param  Request  $request  La solicitud HTTP que puede contener parámetros de filtrado
     * @return JsonResponse Respuesta JSON con el listado de terceros
     *
     * @queryParam tipo string Filtrar por tipo de tercero (Natural, Juridico). Example: Natural
     * @queryParam search string Buscar por nombre/razón social o documento. Example: "Juan"
     * @queryParam divi_poli_id integer Filtrar por división política. Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Terceros obtenidos exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "divi_poli_id": 1,
     *       "num_docu_nit": "12345678",
     *       "nom_razo_soci": "Juan Pérez",
     *       "direccion": "Calle 123",
     *       "telefono": "300123456",
     *       "email": "juan@example.com",
     *       "tipo": "Natural",
     *       "notifica_email": true,
     *       "notifica_msm": false,
     *       "created_at": "2024-01-15T10:30:00.000000Z",
     *       "updated_at": "2024-01-15T10:30:00.000000Z",
     *       "division_politica": {
     *         "id": 1,
     *         "codigo": "11001",
     *         "nombre": "Bogotá",
     *         "tipo": "M"
     *       }
     *     }
     *   ]
     * }
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener terceros",
     *   "error": "Error message"
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = GestionTercero::with('divisionPolitica');

            // Filtro por tipo de tercero
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            // Filtro por división política
            if ($request->filled('divi_poli_id')) {
                $query->where('divi_poli_id', $request->divi_poli_id);
            }

            // Búsqueda por nombre/razón social o documento
            if ($request->filled('search')) {
                $search = mb_substr((string) $request->search, 0, 100);
                $query->where(function ($q) use ($search) {
                    $q->where('nom_razo_soci', 'like', "%{$search}%")
                        ->orWhere('num_docu_nit', 'like', "%{$search}%");
                });
            }

            // Ordenar por nombre
            $query->orderBy('nom_razo_soci', 'asc');

            // M16-02: siempre paginado; nunca se devuelve la tabla completa
            $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
            $terceros = $query->paginate($perPage);

            return $this->successResponse($terceros, 'Terceros obtenidos exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            Log::error('GestionTercero: Error al obtener terceros', ['error' => $e->getMessage()]);

            return $this->errorResponse('Error al obtener terceros', null, 500);
        }
    }

    /**
     * Obtiene la vista 360 de un tercero por su número de identificación.
     * B24: delega en la consulta integral, que filtra cada fuente por los permisos del usuario
     * (antes listaba todos los radicados y expedientes del tercero sin control de acceso).
     */
    public function showHistory(string $identificacion, ConsultaIntegralTerceroService $consulta): JsonResponse
    {
        try {
            $tercero = GestionTercero::where('num_docu_nit', $identificacion)
                ->with('divisionPolitica')
                ->first();

            if (! $tercero) {
                return $this->errorResponse('Tercero no encontrado', null, 404);
            }

            $datos = $consulta->para($tercero, request()->user());

            return $this->successResponse([
                ...$datos,
                'tercero' => $tercero,
                'total_tramites' => $datos['totales']['radicados_recibidos'] + $datos['totales']['radicados_enviados'],
            ], 'Historial documental obtenido exitosamente');

        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            Log::error('GestionTercero: Error al obtener historial del tercero', ['error' => $e->getMessage()]);

            return $this->errorResponse('Error al obtener historial del tercero', null, 500);
        }
    }

    /**
     * B24 (diseño de expedientes §6.4): "Todo lo del tercero", de solo lectura y filtrado por
     * los permisos del usuario. No es una unidad archivística.
     */
    public function expedienteIntegral(GestionTercero $tercero, ConsultaIntegralTerceroService $consulta): JsonResponse
    {
        return $this->successResponse(
            $consulta->para($tercero, request()->user()),
            'Consulta integral del tercero'
        );
    }

    /**
     * Crea un nuevo tercero en el sistema.
     *
     * @param  GestionTerceroRequest  $request  Datos validados del tercero
     * @return JsonResponse Respuesta JSON con el tercero creado
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Tercero creado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "divi_poli_id": 1,
     *     "num_docu_nit": "12345678",
     *     "nom_razo_soci": "Juan Pérez",
     *     "tipo": "Natural",
     *     "division_politica": {
     *       "id": 1,
     *       "nombre": "Bogotá"
     *     }
     *   }
     * }
     */
    public function store(GestionTerceroRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $tercero = GestionTercero::create($request->validated());

            DB::commit();

            return $this->successResponse(
                $tercero->load('divisionPolitica'),
                'Tercero creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            DB::rollBack();

            Log::error('GestionTercero: Error al crear el tercero', ['error' => $e->getMessage()]);

            return $this->errorResponse('Error al crear el tercero', null, 500);
        }
    }

    /**
     * Obtiene un tercero específico por su ID.
     *
     * @param  GestionTercero  $tercero  El tercero a mostrar (inyectado por Laravel)
     * @return JsonResponse Respuesta JSON con el tercero
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Tercero encontrado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "divi_poli_id": 1,
     *     "num_docu_nit": "12345678",
     *     "nom_razo_soci": "Juan Pérez",
     *     "division_politica": {
     *       "id": 1,
     *       "nombre": "Bogotá"
     *     }
     *   }
     * }
     */
    public function show(GestionTercero $tercero): JsonResponse
    {
        try {
            return $this->successResponse(
                $tercero->load('divisionPolitica'),
                'Tercero encontrado exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            Log::error('GestionTercero: Error al obtener el tercero', ['error' => $e->getMessage()]);

            return $this->errorResponse('Error al obtener el tercero', null, 500);
        }
    }

    /**
     * Actualiza un tercero existente en el sistema.
     *
     * @param  GestionTerceroRequest  $request  Datos validados del tercero
     * @param  GestionTercero  $tercero  El tercero a actualizar (inyectado por Laravel)
     * @return JsonResponse Respuesta JSON con el tercero actualizado
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Tercero actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "divi_poli_id": 1,
     *     "num_docu_nit": "12345678",
     *     "nom_razo_soci": "Juan Pérez",
     *     "division_politica": {
     *       "id": 1,
     *       "nombre": "Bogotá"
     *     }
     *   }
     * }
     */
    public function update(GestionTerceroRequest $request, GestionTercero $tercero): JsonResponse
    {
        try {
            DB::beginTransaction();

            $tercero->update($request->validated());

            DB::commit();

            return $this->successResponse(
                $tercero->load('divisionPolitica'),
                'Tercero actualizado exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            DB::rollBack();

            Log::error('GestionTercero: Error al actualizar el tercero', ['error' => $e->getMessage()]);

            return $this->errorResponse('Error al actualizar el tercero', null, 500);
        }
    }

    /**
     * Elimina un tercero del sistema.
     *
     * @param  GestionTercero  $tercero  El tercero a eliminar (inyectado por Laravel)
     * @return JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Tercero eliminado exitosamente"
     * }
     */
    public function destroy(GestionTercero $tercero): JsonResponse
    {
        // M16-05: un tercero con radicados o PQRS no se elimina (la FK de radicados fallaba con 500 y SQL
        // en la respuesta; la de PQRS es "set null" y dejaba la petición sin peticionario). El borrado de un
        // tercero sin uso sigue siendo físico: es la supresión que exige la Ley 1581.
        $usos = array_filter([
            'radicados recibidos' => DB::table('ventanilla_radica_reci')->where('tercero_id', $tercero->id)->count(),
            'radicados enviados' => DB::table('ventanilla_radica_enviados')->where('tercero_id', $tercero->id)->count(),
            'PQRS' => DB::table('ventanilla_pqrs')->where('gestion_tercero_id', $tercero->id)->count(),
        ]);

        if ($usos) {
            $detalle = implode(', ', array_map(fn ($n, $tipo) => "$n $tipo", $usos, array_keys($usos)));

            return $this->conflictResponse("No se puede eliminar el tercero: tiene $detalle asociados.");
        }

        try {
            DB::beginTransaction();

            $tercero->delete();

            DB::commit();

            return $this->successResponse(null, 'Tercero eliminado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            DB::rollBack();

            Log::error('GestionTercero: Error al eliminar el tercero', ['error' => $e->getMessage()]);

            return $this->errorResponse('Error al eliminar el tercero', null, 500);
        }
    }

    /**
     * Obtiene estadísticas generales de los terceros del sistema.
     *
     * Este método proporciona información estadística básica sobre los terceros,
     * incluyendo totales por tipo y configuración de notificaciones.
     *
     * @return JsonResponse Respuesta JSON con las estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas de terceros obtenidas exitosamente",
     *   "data": {
     *     "total_terceros": 150,
     *     "total_naturales": 120,
     *     "total_juridicos": 30,
     *     "total_con_email": 140,
     *     "total_con_sms": 85
     *   }
     * }
     */
    public function estadisticas(): JsonResponse
    {
        try {
            // Optimización: Una sola query para obtener todas las estadísticas
            $stats = GestionTercero::selectRaw('
                COUNT(*) as total_terceros,
                SUM(CASE WHEN tipo = "Natural" THEN 1 ELSE 0 END) as total_naturales,
                SUM(CASE WHEN tipo = "Juridico" THEN 1 ELSE 0 END) as total_juridicos,
                SUM(CASE WHEN notifica_email = 1 THEN 1 ELSE 0 END) as total_con_email,
                SUM(CASE WHEN notifica_msm = 1 THEN 1 ELSE 0 END) as total_con_sms
            ')->first();

            return $this->successResponse($stats, 'Estadísticas de terceros obtenidas exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            Log::error('GestionTercero: Error al obtener las estadísticas', ['error' => $e->getMessage()]);

            return $this->errorResponse('Error al obtener las estadísticas', null, 500);
        }
    }
}
