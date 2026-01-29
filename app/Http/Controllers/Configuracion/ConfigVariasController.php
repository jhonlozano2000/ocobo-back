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

            // Agregar URL del logo cuando la clave corresponde
            if ($configs instanceof \Illuminate\Pagination\AbstractPaginator) {
                $configs->getCollection()->transform(function (ConfigVarias $config) {
                    if ($config->clave === 'logo_empresa') {
                        $config->logo_url = $config->getArchivoUrl('valor', 'otros_archivos');
                    }
                    return $config;
                });
            } else {
                $configs = $configs->map(function (ConfigVarias $config) {
                    if ($config->clave === 'logo_empresa') {
                        $config->logo_url = $config->getArchivoUrl('valor', 'otros_archivos');
                    }
                    return $config;
                });
            }

            return $this->successResponse($configs, 'Listado de configuraciones obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de configuraciones', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva configuración en el sistema.
     *
     * Este método permite registrar una nueva configuración en el sistema
     * usando su clave como identificador. También maneja la subida de archivos
     * para configuraciones específicas como el logo de la empresa.
     *
     * @param StoreConfigVariasRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la configuración creada
     *
     * @bodyParam clave string required Clave única de la configuración. Example: "app_name"
     * @bodyParam valor string required Valor de la configuración. Example: "Sistema de Gestión"
     * @bodyParam descripcion string Descripción de la configuración. Example: "Nombre de la aplicación"
     * @bodyParam tipo string required Tipo de configuración. Example: "sistema"
     * @bodyParam estado boolean required Estado de la configuración (activo/inactivo). Example: true
     * @bodyParam archivo file Archivo a subir (para configuraciones como logo_empresa). Example: "logo.jpg"
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
     *   "message": "Error de validación",
     *   "errors": {
     *     "clave": ["La clave ya está en uso."],
     *     "valor": ["El valor es obligatorio."]
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

            // Manejar subida de archivos para configuraciones específicas
            if ($validatedData['clave'] === 'logo_empresa') {
                // Buscar cualquier archivo en la request
                $archivos = $request->allFiles();

                if (!empty($archivos)) {
                    // Tomar el primer archivo encontrado
                    $campoArchivo = array_keys($archivos)[0];
                    $nuevoLogo = ConfigVarias::guardarLogoEmpresa($request, $campoArchivo);
                    if ($nuevoLogo) {
                        $validatedData['valor'] = $nuevoLogo;
                    }
                } elseif (!isset($validatedData['valor']) || empty($validatedData['valor'])) {
                    // Si no hay archivo y no hay valor, retornar error
                    return $this->errorResponse('Error de validación', ['valor' => ['El valor es obligatorio cuando no se proporciona un archivo.']], 422);
                }
            } elseif (!isset($validatedData['valor']) || empty($validatedData['valor'])) {
                // Para otras configuraciones, validar que se proporcione un valor
                return $this->errorResponse('Error de validación', ['valor' => ['El valor es obligatorio.']], 422);
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
     * Actualiza una configuración existente en el sistema.
     *
     * Este método permite modificar el valor de una configuración existente
     * usando su clave como identificador. También maneja la subida de archivos
     * para configuraciones específicas como el logo de la empresa.
     *
     * @param UpdateConfigVariasRequest $request La solicitud HTTP validada
     * @param string $clave La clave de la configuración a actualizar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la configuración actualizada
     *
     * @bodyParam valor string required Nuevo valor de la configuración. Example: "Nuevo Sistema"
     * @bodyParam descripcion string Descripción de la configuración. Example: "Nombre actualizado de la aplicación"
     * @bodyParam tipo string Tipo de configuración. Example: "sistema"
     * @bodyParam estado boolean Estado de la configuración (activo/inactivo). Example: true
     * @bodyParam archivo file Archivo a subir (para configuraciones como logo_empresa). Example: "logo.jpg"
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
     *   "message": "Error de validación",
     *   "errors": {
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

            // Manejar subida de archivos para configuraciones específicas
            if ($clave === 'logo_empresa') {
                // Buscar cualquier archivo en la request
                $archivos = $request->allFiles();

                if (!empty($archivos)) {
                    // Tomar el primer archivo encontrado
                    $campoArchivo = array_keys($archivos)[0];
                    $nuevoLogo = ConfigVarias::guardarLogoEmpresa($request, $campoArchivo);
                    if ($nuevoLogo) {
                        $validatedData['valor'] = $nuevoLogo;
                    }
                }
            }

            // Obtener solo los campos que están presentes en la petición
            $allowedFields = ['valor', 'descripcion', 'tipo', 'estado'];
            $dataToUpdate = [];
            foreach ($allowedFields as $field) {
                if ($request->has($field)) {
                    $dataToUpdate[$field] = $request->input($field);
                }
            }

            // Actualizar el modelo con los datos
            if (!empty($dataToUpdate)) {
                $config->fill($dataToUpdate);
                $config->save();
            }

            // Refrescar el modelo para obtener los datos actualizados
            $config->refresh();

            DB::commit();

            return $this->successResponse($config, 'Configuración actualizada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la configuración', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene la configuración de numeración unificada.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Configuración de numeración unificada obtenida exitosamente",
     *   "data": {
     *     "numeracion_unificada": true,
     *     "descripcion": "Define si la numeración de radicados es unificada o por ventanilla"
     *   }
     * }
     */
    public function getNumeracionUnificada()
    {
        try {
            $numeracionUnificada = ConfigVarias::getNumeracionUnificada();

            return $this->successResponse([
                'numeracion_unificada' => $numeracionUnificada,
                'descripcion' => 'Define si la numeración de radicados es unificada o por ventanilla'
            ], 'Configuración de numeración unificada obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la configuración de numeración unificada', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza la configuración de numeración unificada.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @bodyParam numeracion_unificada boolean Configuración de numeración unificada. Example: true
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Configuración de numeración unificada actualizada exitosamente",
     *   "data": {
     *     "numeracion_unificada": true
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "numeracion_unificada": ["El campo numeración unificada es obligatorio."]
     *   }
     * }
     */
    public function updateNumeracionUnificada(Request $request)
    {
        try {
            $request->validate([
                'numeracion_unificada' => [
                    'required',
                    'boolean'
                ]
            ], [
                'numeracion_unificada.required' => 'El campo numeración unificada es obligatorio.',
                'numeracion_unificada.boolean' => 'El campo numeración unificada debe ser verdadero o falso.'
            ]);

            $numeracionUnificada = $request->boolean('numeracion_unificada');
            ConfigVarias::setNumeracionUnificada($numeracionUnificada);

            return $this->successResponse([
                'numeracion_unificada' => $numeracionUnificada
            ], 'Configuración de numeración unificada actualizada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar la configuración de numeración unificada', $e->getMessage(), 500);
        }
    }
}
