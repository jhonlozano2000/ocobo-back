<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\GestionTerceroRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Gestion\GestionTercero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GestionTerceroController extends Controller
{
    use ApiResponseTrait;
    /**
     * Obtiene un listado de todos los terceros del sistema.
     *
     * Este método retorna todos los terceros con su relación de división política.
     * Incluye opciones de filtrado, búsqueda y paginación.
     *
     * @param Request $request La solicitud HTTP que puede contener parámetros de filtrado
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
     *
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
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nom_razo_soci', 'like', "%{$search}%")
                        ->orWhere('num_docu_nit', 'like', "%{$search}%");
                });
            }

            // Ordenar por nombre
            $query->orderBy('nom_razo_soci', 'asc');

            // Paginación si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $terceros = $query->paginate($perPage);
            } else {
                $terceros = $query->get();
            }

            return $this->successResponse($terceros, 'Terceros obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener terceros', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo tercero en el sistema.
     *
     * @param GestionTerceroRequest $request Datos validados del tercero
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
            DB::rollBack();
            return $this->errorResponse('Error al crear el tercero', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un tercero específico por su ID.
     *
     * @param GestionTercero $tercero El tercero a mostrar (inyectado por Laravel)
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
            return $this->errorResponse('Error al obtener el tercero', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un tercero existente en el sistema.
     *
     * @param GestionTerceroRequest $request Datos validados del tercero
     * @param GestionTercero $tercero El tercero a actualizar (inyectado por Laravel)
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
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el tercero', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un tercero del sistema.
     *
     * @param GestionTercero $tercero El tercero a eliminar (inyectado por Laravel)
     * @return JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Tercero eliminado exitosamente"
     * }
     */
    public function destroy(GestionTercero $tercero): JsonResponse
    {
        try {
            DB::beginTransaction();

            $tercero->delete();

            DB::commit();

            return $this->successResponse(null, 'Tercero eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el tercero', $e->getMessage(), 500);
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
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }

    /**
     * Busca terceros por documento o nombre.
     *
     * Este método permite búsqueda rápida de terceros para autocompletado.
     *
     * @param Request $request La solicitud HTTP con el término de búsqueda
     * @return JsonResponse Respuesta JSON con los terceros encontrados
     *
     * @queryParam q string required El término de búsqueda (mínimo 3 caracteres). Example: "Juan"
     * @queryParam limit integer Límite de resultados (por defecto: 10). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Búsqueda completada exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "num_docu_nit": "12345678",
     *       "nom_razo_soci": "Juan Pérez",
     *       "tipo": "Natural"
     *     }
     *   ]
     * }
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:3|max:50',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $query = $request->input('q');
            $limit = $request->input('limit', 10);

            $terceros = GestionTercero::where(function ($q) use ($query) {
                $q->where('num_docu_nit', 'like', "%{$query}%")
                    ->orWhere('nom_razo_soci', 'like', "%{$query}%");
            })
                ->select('id', 'num_docu_nit', 'nom_razo_soci', 'tipo')
                ->orderBy('nom_razo_soci')
                ->limit($limit)
                ->get();

            return $this->successResponse($terceros, 'Búsqueda completada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error en la búsqueda', $e->getMessage(), 500);
        }
    }
}
