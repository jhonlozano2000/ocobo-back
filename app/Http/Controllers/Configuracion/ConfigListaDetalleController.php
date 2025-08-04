<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\StoreConfigListaDetalleRequest;
use App\Http\Requests\Configuracion\UpdateConfigListaDetalleRequest;
use App\Models\Configuracion\ConfigListaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigListaDetalleController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todos los detalles de listas del sistema.
     *
     * Este método retorna todos los detalles de listas con sus relaciones
     * de lista asociadas. Es útil para interfaces de administración donde
     * se necesita mostrar la estructura completa de las listas maestras.
     *
     * @param Request $request La solicitud HTTP que puede contener parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de detalles
     *
     * @queryParam lista_id integer Filtrar por ID de lista. Example: 1
     * @queryParam search string Buscar por código o nombre. Example: "CC"
     * @queryParam estado integer Filtrar por estado (0 inactivo, 1 activo). Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de detalles de listas obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "lista_id": 1,
     *       "codigo": "CC",
     *       "nombre": "Cédula de Ciudadanía",
     *       "descripcion": "Documento de identidad colombiano",
     *       "orden": 1,
     *       "estado": 1,
     *       "lista": {
     *         "id": 1,
     *         "cod": "TIPOS",
     *         "nombre": "Tipos de Documento"
     *       }
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de detalles",
     *   "error": "Error message"
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = ConfigListaDetalle::with('lista');

            // Aplicar filtros si se proporcionan
            if ($request->filled('lista_id')) {
                $query->where('lista_id', $request->lista_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('codigo', 'like', "%{$search}%")
                        ->orWhere('nombre', 'like', "%{$search}%");
                });
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            // Ordenar por orden y nombre
            $query->orderBy('orden', 'asc')->orderBy('nombre', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $detalles = $query->paginate($perPage);
            } else {
                $detalles = $query->get();
            }

            return $this->successResponse($detalles, 'Listado de detalles de listas obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de detalles', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo detalle de lista en el sistema.
     *
     * Este método permite crear un nuevo detalle de lista con validación
     * de datos y conversión automática del campo estado.
     *
     * @param StoreConfigListaDetalleRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el detalle creado
     *
     * @bodyParam lista_id integer required ID de la lista asociada. Example: 1
     * @bodyParam codigo string required Código del detalle. Example: "CC"
     * @bodyParam nombre string required Nombre del detalle. Example: "Cédula de Ciudadanía"
     * @bodyParam descripcion string Descripción del detalle. Example: "Documento de identidad colombiano"
     * @bodyParam orden integer Orden de presentación. Example: 1
     * @bodyParam estado boolean Estado del detalle (activo/inactivo). Example: true
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Detalle de lista creado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "lista_id": 1,
     *     "codigo": "CC",
     *     "nombre": "Cédula de Ciudadanía",
     *     "descripcion": "Documento de identidad colombiano",
     *     "orden": 1,
     *     "estado": 1,
     *     "lista": {
     *       "id": 1,
     *       "cod": "TIPOS",
     *       "nombre": "Tipos de Documento"
     *     }
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "lista_id": ["La lista seleccionada no existe."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear el detalle",
     *   "error": "Error message"
     * }
     */
    public function store(StoreConfigListaDetalleRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $detalle = ConfigListaDetalle::create($validatedData);

            DB::commit();

            return $this->successResponse(
                $detalle->load('lista'),
                'Detalle de lista creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el detalle', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un detalle de lista específico por su ID.
     *
     * Este método permite obtener los detalles de un elemento específico
     * de una lista maestra, incluyendo su lista asociada.
     *
     * @param ConfigListaDetalle $listaDetalle El detalle a obtener (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el detalle
     *
     * @urlParam listaDetalle integer required El ID del detalle. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Detalle de lista encontrado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "lista_id": 1,
     *     "codigo": "CC",
     *     "nombre": "Cédula de Ciudadanía",
     *     "descripcion": "Documento de identidad colombiano",
     *     "orden": 1,
     *     "estado": 1,
     *     "lista": {
     *       "id": 1,
     *       "cod": "TIPOS",
     *       "nombre": "Tipos de Documento"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Detalle de lista no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el detalle",
     *   "error": "Error message"
     * }
     */
    public function show(ConfigListaDetalle $listaDetalle)
    {
        try {
            return $this->successResponse(
                $listaDetalle->load('lista'),
                'Detalle de lista encontrado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el detalle', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un detalle de lista existente en el sistema.
     *
     * Este método permite modificar los datos de un detalle de lista existente,
     * incluyendo conversión automática del campo estado.
     *
     * @param UpdateConfigListaDetalleRequest $request La solicitud HTTP validada
     * @param ConfigListaDetalle $listaDetalle El detalle a actualizar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el detalle actualizado
     *
     * @bodyParam lista_id integer ID de la lista asociada. Example: 1
     * @bodyParam codigo string Código del detalle. Example: "CC"
     * @bodyParam nombre string Nombre del detalle. Example: "Cédula de Ciudadanía"
     * @bodyParam descripcion string Descripción del detalle. Example: "Documento de identidad colombiano"
     * @bodyParam orden integer Orden de presentación. Example: 1
     * @bodyParam estado boolean Estado del detalle (activo/inactivo). Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Detalle de lista actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "lista_id": 1,
     *     "codigo": "CC",
     *     "nombre": "Cédula de Ciudadanía",
     *     "descripcion": "Documento de identidad colombiano",
     *     "orden": 1,
     *     "estado": 1
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "orden": ["El orden debe ser al menos 1."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el detalle",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateConfigListaDetalleRequest $request, ConfigListaDetalle $listaDetalle)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $listaDetalle->update($validatedData);

            DB::commit();

            return $this->successResponse(
                $listaDetalle->load('lista'),
                'Detalle de lista actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el detalle', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un detalle de lista del sistema.
     *
     * Este método permite eliminar un detalle de lista específico del sistema.
     * Se recomienda verificar que no tenga dependencias antes de eliminar.
     *
     * @param ConfigListaDetalle $listaDetalle El detalle a eliminar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam listaDetalle integer required El ID del detalle a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Detalle de lista eliminado exitosamente"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar el detalle",
     *   "error": "Error message"
     * }
     */
    public function destroy(ConfigListaDetalle $listaDetalle)
    {
        try {
            DB::beginTransaction();

            $listaDetalle->delete();

            DB::commit();

            return $this->successResponse(null, 'Detalle de lista eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el detalle', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas básicas de las listas y detalles del sistema.
     *
     * Este método proporciona información estadística simple sobre el total
     * de listas y el total de detalles de listas en el sistema.
     *
     * @param Request $request La solicitud HTTP
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "total_listas": 12,
     *     "total_detalles": 150
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las estadísticas",
     *   "error": "Error message"
     * }
     */
    public function estadisticas(Request $request)
    {
        try {
            // Total de listas que tienen detalles
            $totalListas = ConfigListaDetalle::distinct('lista_id')->count();

            // Total de detalles de listas
            $totalDetalles = ConfigListaDetalle::count();

            $estadisticas = [
                'total_listas' => $totalListas,
                'total_detalles' => $totalDetalles
            ];

            return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }
}
