<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\StoreConfigVariasRequest;
use App\Http\Requests\Configuracion\UpdateConfigVariasRequest;
use App\Models\Configuracion\ConfigVarias;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigVariasController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todas las configuraciones varias del sistema.
     *
     * Este método retorna todas las configuraciones varias registradas en el sistema.
     * Es útil para interfaces de administración donde se necesita mostrar
     * la configuración general del sistema.
     *
     * @param Request $request La solicitud HTTP que puede contener parámetros de filtrado
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de configuraciones
     *
     * @queryParam search string Buscar por clave o valor. Example: "app_name"
     * @queryParam tipo string Filtrar por tipo de configuración. Example: "sistema"
     * @queryParam estado integer Filtrar por estado (0 inactivo, 1 activo). Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 15). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de configuraciones obtenido exitosamente",
     *   "data": [
     *     {
     *       "id": 1,
     *       "clave": "app_name",
     *       "valor": "Sistema de Gestión",
     *       "descripcion": "Nombre de la aplicación",
     *       "tipo": "sistema",
     *       "estado": 1,
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de configuraciones",
     *   "error": "Error message"
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = ConfigVarias::query();

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('clave', 'like', "%{$search}%")
                        ->orWhere('valor', 'like', "%{$search}%");
                });
            }

            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            // Ordenar por clave
            $query->orderBy('clave', 'asc');

            // Paginar si se solicita
            if ($request->filled('per_page')) {
                $perPage = $request->per_page;
                $configs = $query->paginate($perPage);
            } else {
                $configs = $query->get();
            }

            return $this->successResponse($configs, 'Listado de configuraciones obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de configuraciones', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva configuración en el sistema.
     *
     * Este método permite crear una nueva configuración con validación
     * de datos y conversión automática del campo estado.
     *
     * @param StoreConfigVariasRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la configuración creada
     *
     * @bodyParam clave string required Clave única de la configuración. Example: "app_name"
     * @bodyParam valor string required Valor de la configuración. Example: "Sistema de Gestión"
     * @bodyParam descripcion string Descripción de la configuración. Example: "Nombre de la aplicación"
     * @bodyParam tipo string Tipo de configuración. Example: "sistema"
     * @bodyParam estado boolean Estado de la configuración (activo/inactivo). Example: true
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Configuración creada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "clave": "app_name",
     *     "valor": "Sistema de Gestión",
     *     "descripcion": "Nombre de la aplicación",
     *     "tipo": "sistema",
     *     "estado": 1
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "clave": ["La clave ya está en uso, por favor elija otra."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear la configuración",
     *   "error": "Error message"
     * }
     */
    public function store(StoreConfigVariasRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $config = ConfigVarias::create($validatedData);

            DB::commit();

            return $this->successResponse($config, 'Configuración creada exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear la configuración', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una configuración específica por su clave.
     *
     * Este método permite obtener los detalles de una configuración específica
     * usando su clave como identificador.
     *
     * @param string $clave La clave de la configuración
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la configuración
     *
     * @urlParam clave string required La clave de la configuración. Example: "app_name"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Configuración encontrada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "clave": "app_name",
     *     "valor": "Sistema de Gestión",
     *     "descripcion": "Nombre de la aplicación",
     *     "tipo": "sistema",
     *     "estado": 1
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Configuración no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la configuración",
     *   "error": "Error message"
     * }
     */
    public function show(string $clave)
    {
        try {
            $config = ConfigVarias::where('clave', $clave)->first();

            if (!$config) {
                return $this->errorResponse('Configuración no encontrada', null, 404);
            }

            return $this->successResponse($config, 'Configuración encontrada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la configuración', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una configuración existente en el sistema.
     *
     * Este método permite modificar el valor de una configuración existente
     * usando su clave como identificador.
     *
     * @param UpdateConfigVariasRequest $request La solicitud HTTP validada
     * @param string $clave La clave de la configuración a actualizar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la configuración actualizada
     *
     * @bodyParam valor string required Nuevo valor de la configuración. Example: "Nuevo Sistema"
     * @bodyParam descripcion string Descripción de la configuración. Example: "Nombre actualizado de la aplicación"
     * @bodyParam tipo string Tipo de configuración. Example: "sistema"
     * @bodyParam estado boolean Estado de la configuración (activo/inactivo). Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Configuración actualizada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "clave": "app_name",
     *     "valor": "Nuevo Sistema",
     *     "descripcion": "Nombre actualizado de la aplicación",
     *     "tipo": "sistema",
     *     "estado": 1
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Configuración no encontrada"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "valor": ["El valor es obligatorio."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la configuración",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateConfigVariasRequest $request, string $clave)
    {
        try {
            DB::beginTransaction();

            $config = ConfigVarias::where('clave', $clave)->first();

            if (!$config) {
                return $this->errorResponse('Configuración no encontrada', null, 404);
            }

            $validatedData = $request->validated();

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $config->update($validatedData);

            DB::commit();

            return $this->successResponse($config, 'Configuración actualizada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la configuración', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una configuración del sistema.
     *
     * Este método permite eliminar una configuración específica del sistema
     * usando su clave como identificador.
     *
     * @param string $clave La clave de la configuración a eliminar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam clave string required La clave de la configuración a eliminar. Example: "app_name"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Configuración eliminada exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Configuración no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar la configuración",
     *   "error": "Error message"
     * }
     */
    public function destroy(string $clave)
    {
        try {
            DB::beginTransaction();

            $config = ConfigVarias::where('clave', $clave)->first();

            if (!$config) {
                return $this->errorResponse('Configuración no encontrada', null, 404);
            }

            $config->delete();

            DB::commit();

            return $this->successResponse(null, 'Configuración eliminada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar la configuración', $e->getMessage(), 500);
        }
    }
}
