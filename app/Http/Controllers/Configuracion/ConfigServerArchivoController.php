<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\StoreConfigServerArchivoRequest;
use App\Http\Requests\Configuracion\UpdateConfigServerArchivoRequest;
use App\Models\Configuracion\ConfigServerArchivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigServerArchivoController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todos los servidores de archivos del sistema.
     *
     * Este método retorna todos los servidores de archivos con sus relaciones
     * de proceso asociadas. Es útil para interfaces de administración donde
     * se necesita mostrar la configuración de servidores de archivos.
     *
     * @param Request $request La solicitud HTTP que puede contener parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de servidores
     *
     * @queryParam search string Buscar por host, usuario, ruta o detalle. Example: "ftp"
     * @queryParam estado integer Filtrar por estado (0 inactivo, 1 activo). Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de servidores de archivos obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "proceso_id": 1,
     *       "host": "192.168.1.1",
     *       "ruta": "/archivos",
     *       "user": "ftpuser",
     *       "detalle": "Servidor FTP Principal",
     *       "estado": 1,
     *       "proceso": {
     *         "id": 1,
     *         "nombre": "Proceso Principal"
     *       }
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de servidores",
     *   "error": "Error message"
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = ConfigServerArchivo::with('proceso');

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('host', 'like', "%{$search}%")
                        ->orWhere('user', 'like', "%{$search}%")
                        ->orWhere('ruta', 'like', "%{$search}%")
                        ->orWhere('detalle', 'like', "%{$search}%");
                });
            }

            if ($request->filled('estado')) {
                $estado = filter_var($request->estado, FILTER_VALIDATE_BOOLEAN);
                $query->where('estado', $estado);
            }

            // Ordenar por host
            $query->orderBy('host', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $servers = $query->paginate($perPage);
            } else {
                $servers = $query->get();
            }

            return $this->successResponse($servers, 'Listado de servidores de archivos obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de servidores', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo servidor de archivos en el sistema.
     *
     * Este método permite crear un nuevo servidor de archivos con validación
     * de datos y conversión automática del campo estado.
     *
     * @param StoreConfigServerArchivoRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el servidor creado
     *
     * @bodyParam nombre string required Nombre del servidor. Example: "Servidor FTP Principal"
     * @bodyParam url string required URL del servidor. Example: "ftp://example.com"
     * @bodyParam puerto integer required Puerto del servidor. Example: 21
     * @bodyParam usuario string required Usuario de acceso. Example: "ftpuser"
     * @bodyParam password string required Contraseña de acceso. Example: "password123"
     * @bodyParam ruta_base string required Ruta base en el servidor. Example: "/archivos"
     * @bodyParam proceso_id integer ID del proceso asociado. Example: 1
     * @bodyParam estado boolean Estado del servidor (activo/inactivo). Example: true
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Servidor de archivos creado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombre": "Servidor FTP Principal",
     *     "url": "ftp://example.com",
     *     "puerto": 21,
     *     "usuario": "ftpuser",
     *     "ruta_base": "/archivos",
     *     "estado": 1,
     *     "proceso": null
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "url": ["La URL debe tener un formato válido."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear el servidor",
     *   "error": "Error message"
     * }
     */
    public function store(StoreConfigServerArchivoRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $server = ConfigServerArchivo::create($validatedData);

            DB::commit();

            return $this->successResponse(
                $server->load('proceso'),
                'Servidor de archivos creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el servidor', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un servidor de archivos específico por su ID.
     *
     * Este método permite obtener los detalles de un servidor de archivos específico,
     * incluyendo su proceso asociado.
     *
     * @param ConfigServerArchivo $configServerArchivo El servidor a obtener (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el servidor
     *
     * @urlParam configServerArchivo integer required El ID del servidor. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Servidor de archivos encontrado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombre": "Servidor FTP Principal",
     *     "url": "ftp://example.com",
     *     "puerto": 21,
     *     "usuario": "ftpuser",
     *     "ruta_base": "/archivos",
     *     "estado": 1,
     *     "proceso": {
     *       "id": 1,
     *       "nombre": "Proceso Principal"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Servidor de archivos no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el servidor",
     *   "error": "Error message"
     * }
     */
    public function show(ConfigServerArchivo $configServerArchivo)
    {
        try {
            return $this->successResponse(
                $configServerArchivo->load('proceso'),
                'Servidor de archivos encontrado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el servidor', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un servidor de archivos existente en el sistema.
     *
     * Este método permite modificar los datos de un servidor de archivos existente,
     * incluyendo conversión automática del campo estado.
     *
     * @param UpdateConfigServerArchivoRequest $request La solicitud HTTP validada
     * @param ConfigServerArchivo $configServerArchivo El servidor a actualizar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el servidor actualizado
     *
     * @bodyParam nombre string Nombre del servidor. Example: "Servidor FTP Principal"
     * @bodyParam url string URL del servidor. Example: "ftp://example.com"
     * @bodyParam puerto integer Puerto del servidor. Example: 21
     * @bodyParam usuario string Usuario de acceso. Example: "ftpuser"
     * @bodyParam password string Contraseña de acceso. Example: "password123"
     * @bodyParam ruta_base string Ruta base en el servidor. Example: "/archivos"
     * @bodyParam proceso_id integer ID del proceso asociado. Example: 1
     * @bodyParam estado boolean Estado del servidor (activo/inactivo). Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Servidor de archivos actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombre": "Servidor FTP Principal",
     *     "url": "ftp://example.com",
     *     "puerto": 21,
     *     "usuario": "ftpuser",
     *     "ruta_base": "/archivos",
     *     "estado": 1
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "puerto": ["El puerto debe ser al menos 1."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el servidor",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateConfigServerArchivoRequest $request, ConfigServerArchivo $configServerArchivo)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Si el password no viene o está vacío, removerlo de los datos a actualizar
            if (!isset($validatedData['password']) || empty($validatedData['password'])) {
                unset($validatedData['password']);
            }

            // Filtrar solo los campos que existen en el modelo
            $validatedData = array_intersect_key($validatedData, array_flip($configServerArchivo->getFillable()));

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            // Actualizar el modelo
            $configServerArchivo->fill($validatedData);
            $configServerArchivo->save();

            // Refrescar el modelo para obtener los datos actualizados
            $configServerArchivo->refresh();

            DB::commit();

            return $this->successResponse(
                $configServerArchivo->load('proceso'),
                'Servidor de archivos actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el servidor', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un servidor de archivos del sistema.
     *
     * Este método permite eliminar un servidor de archivos específico del sistema.
     * Se recomienda verificar que no tenga dependencias antes de eliminar.
     *
     * @param ConfigServerArchivo $configServerArchivo El servidor a eliminar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam configServerArchivo integer required El ID del servidor a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Servidor de archivos eliminado exitosamente"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar el servidor",
     *   "error": "Error message"
     * }
     */
    public function destroy(ConfigServerArchivo $configServerArchivo)
    {
        try {
            DB::beginTransaction();

            $configServerArchivo->delete();

            DB::commit();

            return $this->successResponse(null, 'Servidor de archivos eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el servidor', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas de servidores de archivos del sistema.
     *
     * Este método proporciona estadísticas generales sobre los servidores de archivos,
     * incluyendo el total de servidores, servidores activos/inactivos y distribución
     * por proceso. Es útil para dashboards de administración.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "total_servidores": 5,
     *     "servidores_activos": 4,
     *     "servidores_inactivos": 1,
     *     "servidores_por_proceso": {
     *       "1": 2,
     *       "2": 1
     *     },
     *     "servidores_sin_proceso": 2
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las estadísticas",
     *   "error": "Error message"
     * }
     */
    public function estadisticas()
    {
        try {
            $totalServidores = ConfigServerArchivo::count();
            $servidoresActivos = ConfigServerArchivo::where('estado', 1)->count();
            $servidoresInactivos = ConfigServerArchivo::where('estado', 0)->count();

            // Servidores por proceso con nombre del proceso
            $servidoresPorProceso = DB::table('config_server_archivos as servidor')
                ->join('config_listas_detalles as proceso', 'servidor.proceso_id', '=', 'proceso.id')
                ->selectRaw('proceso.nombre, COUNT(*) as total')
                ->whereNotNull('servidor.proceso_id')
                ->groupBy('proceso.nombre')
                ->pluck('total', 'nombre')
                ->toArray();

            // Servidores sin proceso
            $servidoresSinProceso = ConfigServerArchivo::whereNull('proceso_id')->count();

            $estadisticas = [
                'total_servidores' => $totalServidores,
                'servidores_activos' => $servidoresActivos,
                'servidores_inactivos' => $servidoresInactivos,
                'servidores_por_proceso' => $servidoresPorProceso,
                'servidores_sin_proceso' => $servidoresSinProceso,
            ];

            return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }
}
