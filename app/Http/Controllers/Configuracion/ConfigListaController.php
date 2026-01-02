<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\StoreConfigListaRequest;
use App\Http\Requests\Configuracion\UpdateConfigListaRequest;
use App\Models\Configuracion\ConfigLista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigListaController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todas las listas maestras del sistema.
     *
     * Este método retorna todas las listas maestras con sus detalles asociados.
     * Es útil para interfaces de administración donde se necesita mostrar
     * la estructura completa de las listas.
     *
     * @param Request $request La solicitud HTTP que puede contener parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de listas
     *
     * @queryParam search string Buscar por código o nombre. Example: "TIPOS"
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de listas obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "cod": "TIPOS",
     *       "nombre": "Tipos de Documento",
     *       "detalles": [
     *         {
     *           "id": 1,
     *           "codigo": "CC",
     *           "nombre": "Cédula de Ciudadanía"
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de listas",
     *   "error": "Error message"
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = ConfigLista::with('detalles');

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('cod', 'like', "%{$search}%")
                        ->orWhere('nombre', 'like', "%{$search}%");
                });
            }

            // Ordenar por código
            $query->orderBy('cod', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $listas = $query->paginate($perPage);
            } else {
                $listas = $query->get();
            }

            return $this->successResponse($listas, 'Listado de listas obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de listas', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva lista maestra en el sistema.
     *
     * Este método permite crear una nueva lista maestra con validación
     * de datos y verificación de códigos únicos.
     *
     * @param StoreConfigListaRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista creada
     *
     * @bodyParam cod string required Código único de la lista. Example: "TIPOS"
     * @bodyParam nombre string required Nombre de la lista. Example: "Tipos de Documento"
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Lista creada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "cod": "TIPOS",
     *     "nombre": "Tipos de Documento",
     *     "detalles": []
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "cod": ["El código ya está en uso, por favor elija otro."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear la lista",
     *   "error": "Error message"
     * }
     */
    public function store(StoreConfigListaRequest $request)
    {
        try {
            DB::beginTransaction();

            $lista = ConfigLista::create($request->validated());

            DB::commit();

            return $this->successResponse(
                $lista->load('detalles'),
                'Lista creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
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
     * Actualiza una lista maestra existente en el sistema.
     *
     * Este método permite modificar los datos de una lista maestra existente,
     * manteniendo la integridad de los códigos únicos.
     *
     * @param UpdateConfigListaRequest $request La solicitud HTTP validada
     * @param ConfigLista $lista La lista a actualizar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista actualizada
     *
     * @bodyParam cod string Código único de la lista. Example: "TIPOS"
     * @bodyParam nombre string Nombre de la lista. Example: "Tipos de Documento"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Lista actualizada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "cod": "TIPOS",
     *     "nombre": "Tipos de Documento",
     *     "detalles": []
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "cod": ["El código ya está en uso, por favor elija otro."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la lista",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateConfigListaRequest $request, ConfigLista $lista)
    {
        try {
            DB::beginTransaction();

            $lista->update($request->validated());

            DB::commit();

            return $this->successResponse(
                $lista->load('detalles'),
                'Lista actualizada exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la lista', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene todas las listas maestras con el detalle activas.
     *
     * Este método retorna todas las listas maestras activas.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las listas activas
     */
    public function listasActivasDetalle($id)
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
    public function listaDetalle(Request $request)
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
     * Obtiene todas las listas maestras (cabezas) sin detalles.
     *
     * Este método retorna únicamente las listas maestras (cabezas) sin incluir
     * sus detalles asociados. Útil para obtener un listado simple de las listas.
     *
     * @param Request $request La solicitud HTTP que puede contener parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las listas (cabezas)
     *
     * @queryParam search string Buscar por código o nombre de lista. Example: "TIPOS"
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listas obtenidas exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "cod": "TIPOS",
     *       "nombre": "Tipos de Documento",
     *       "estado": true,
     *       "created_at": "2024-01-15T10:30:00.000000Z",
     *       "updated_at": "2024-01-15T10:30:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las listas",
     *   "error": "Error message"
     * }
     */
    public function listaCabeza(Request $request)
    {
        try {
            $query = ConfigLista::query();

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('cod', 'like', "%{$search}%")
                        ->orWhere('nombre', 'like', "%{$search}%");
                });
            }

            // Ordenar por código
            $query->orderBy('cod', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $listas = $query->paginate($perPage);
            } else {
                $listas = $query->get();
            }

            return $this->successResponse($listas, 'Listas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las listas', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una lista maestra del sistema.
     *
     * Este método permite eliminar una lista maestra específica, verificando
     * que no tenga detalles asociados antes de proceder.
     *
     * @param ConfigLista $lista La lista a eliminar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam lista integer required El ID de la lista a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Lista eliminada exitosamente"
     * }
     *
     * @response 409 {
     *   "status": false,
     *   "message": "No se puede eliminar porque tiene detalles asociados"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar la lista",
     *   "error": "Error message"
     * }
     */
    public function destroy(ConfigLista $lista)
    {
        try {
            DB::beginTransaction();

            // Verificar si tiene detalles asociados
            if ($lista->detalles()->exists()) {
                return $this->errorResponse(
                    'No se puede eliminar porque tiene detalles asociados',
                    null,
                    409
                );
            }

            $lista->delete();

            DB::commit();

            return $this->successResponse(null, 'Lista eliminada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar la lista', $e->getMessage(), 500);
        }
    }
}
