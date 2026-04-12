<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\StoreConfigListaRequest;
use App\Http\Requests\Configuracion\UpdateConfigListaRequest;
use App\Models\Configuracion\ConfigLista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Configuracion\ConfigListaService;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;

class ConfigListaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ConfigListaService $service
    ) {}

    /**
     * Obtiene un listado de todas las listas maestras del sistema.
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->all();
            $listas = $this->service->getAll($filters);

            return $this->successResponse($listas, 'Listado de listas obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de listas', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva lista maestra.
     */
    public function store(StoreConfigListaRequest $request)
    {
        try {
            $lista = $this->service->create($request->validated());

            return $this->successResponse(
                $lista->load('detalles'),
                'Lista creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear la lista', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una lista maestra específica por su ID.
     *
     * Este método permite obtener los detalles de una lista maestra específica,
     * incluyendo todos sus elementos asociados.
     *
     * @param ConfigLista $lista La lista a obtener (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista
     *
     * @urlParam lista integer required El ID de la lista. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Lista encontrada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "cod": "TIPOS",
     *     "nombre": "Tipos de Documento",
     *     "detalles": [
     *       {
     *         "id": 1,
     *         "codigo": "CC",
     *         "nombre": "Cédula de Ciudadanía"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Lista no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la lista",
     *   "error": "Error message"
     * }
     */
    public function show(ConfigLista $lista)
    {
        try {
            return $this->successResponse(
                $lista->load('detalles'),
                'Lista encontrada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la lista', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una lista maestra.
     */
    public function update(UpdateConfigListaRequest $request, int $id)
    {
        try {
            $lista = $this->service->update($id, $request->validated());

            if (!$lista) {
                return $this->errorResponse('Lista no encontrada', null, 404);
            }

            return $this->successResponse(
                $lista->load('detalles'),
                'Lista actualizada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar la lista', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene todas las listas maestras con el detalle activas.
     */
    public function listasActivasDetalle(int $id)
    {
        return $this->successResponse(
            ConfigLista::with(['detalles' => function ($query) {
                $query->where('estado', true);
            }])->where('id', $id)->where('estado', true)->get(),
            'Listas activas y detalles activos obtenidos exitosamente'
        );
    }

    /**
     * Obtiene todas las listas maestras con sus detalles.
     *
     * Este método retorna todas las listas maestras (cabezas) con todos sus detalles asociados.
     * Útil para obtener la estructura completa de todas las listas del sistema.
     *
     * @param Request $request La solicitud HTTP que puede contener parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las listas y sus detalles
     *
     * @queryParam search string Buscar por código o nombre de lista. Example: "TIPOS"
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listas con detalles obtenidas exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "cod": "TIPOS",
     *       "nombre": "Tipos de Documento",
     *       "estado": true,
     *       "detalles": [
     *         {
     *           "id": 1,
     *           "lista_id": 1,
     *           "codigo": "CC",
     *           "nombre": "Cédula de Ciudadanía",
     *           "estado": true
     *         },
     *         {
     *           "id": 2,
     *           "lista_id": 1,
     *           "codigo": "CE",
     *           "nombre": "Cédula de Extranjería",
     *           "estado": true
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las listas con detalles",
     *   "error": "Error message"
     * }
     */
    public function listaDetalle(HttpFoundationRequest $request)
    {
        try {
            // Construir la consulta con join para obtener cabeza y detalle en una estructura plana
            $query = DB::table('config_listas as lista')
                ->leftJoin('config_listas_detalles as detalle', 'lista.id', '=', 'detalle.lista_id')
                ->select([
                    'lista.id',
                    'lista.cod',
                    'lista.nombre',
                    'lista.estado',
                    'lista.created_at',
                    'lista.updated_at',
                    'detalle.id as id_detalle',
                    'detalle.codigo',
                    'detalle.nombre as nombre_detalle',
                    'detalle.estado as estado_detalle'
                ]);

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('lista.cod', 'like', "%{$search}%")
                        ->orWhere('lista.nombre', 'like', "%{$search}%")
                        ->orWhere('detalle.codigo', 'like', "%{$search}%")
                        ->orWhere('detalle.nombre', 'like', "%{$search}%");
                });
            }

            // Ordenar por código de lista y código de detalle
            $query->orderBy('lista.cod', 'asc')
                ->orderBy('detalle.codigo', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $resultados = $query->paginate($perPage);
            } else {
                $resultados = $query->get();
            }

            return $this->successResponse($resultados, 'Listas con detalles obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las listas con detalles', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene solo listas maestras (cabezas).
     */
    public function listaCabeza(Request $request)
    {
        try {
            \Log::info('listaCabeza called', ['filters' => $request->all()]);
            $filters = $request->all();
            $listas = $this->service->getOnlyHeads($filters);
            \Log::info('listaCabeza result', ['count' => $listas->count() ?? 'collection']);

            return $this->successResponse($listas, 'Listas obtenidas exitosamente');
        } catch (\Exception $e) {
            \Log::error('listaCabeza error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->errorResponse('Error al obtener las listas', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una lista maestra.
     */
    public function destroy(int $id)
    {
        try {
            if (!$this->service->delete($id)) {
                return $this->errorResponse(
                    'No se puede eliminar porque tiene detalles asociados',
                    null,
                    409
                );
            }

            return $this->successResponse(null, 'Lista eliminada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar la lista', $e->getMessage(), 500);
        }
    }
}
