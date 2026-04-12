<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\StoreConfigVariasRequest;
use App\Http\Requests\Configuracion\UpdateConfigVariasRequest;
use App\Models\Configuracion\ConfigVarias;
use Illuminate\Http\Request;
use App\Services\Configuracion\ConfigVariasService;

class ConfigVariasController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ConfigVariasService $service
    ) {}

    /**
     * Listado de configuraciones varias.
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->validated();
            $configs = $this->service->getAll($filters);

            return $this->successResponse($configs, 'Listado de configuraciones obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva configuración.
     */
    public function store(StoreConfigVariasRequest $request)
    {
        try {
            $validated = $request->validated();

            if (isset($validated['estado'])) {
                $validated['estado'] = filter_var($validated['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            if ($validated['clave'] === 'logo_empresa') {
                $archivos = $request->allFiles();
                if (!empty($archivos)) {
                    $campo = array_keys($archivos)[0];
                    $nuevoLogo = ConfigVarias::guardarLogoEmpresa($request, $campo);
                    if ($nuevoLogo) {
                        $validated['valor'] = $nuevoLogo;
                    }
                }
            }

            $config = $this->service->create($validated);

            return $this->successResponse($config, 'Configuración creada exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una configuración.
     */
    public function update(UpdateConfigVariasRequest $request, string $clave)
    {
        try {
            $validated = $request->only(['valor', 'descripcion', 'tipo', 'estado']);

            if (isset($validated['estado'])) {
                $validated['estado'] = filter_var($validated['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            if ($clave === 'logo_empresa') {
                $archivos = $request->allFiles();
                if (!empty($archivos)) {
                    $campo = array_keys($archivos)[0];
                    $nuevoLogo = ConfigVarias::guardarLogoEmpresa($request, $campo);
                    if ($nuevoLogo) {
                        $validated['valor'] = $nuevoLogo;
                    }
                }
            }

            $config = $this->service->update($clave, $validated);

            if (!$config) {
                return $this->errorResponse('Configuración no encontrada', null, 404);
            }

            return $this->successResponse($config, 'Configuración actualizada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene numeración unificada.
     */
    public function getNumeracionUnificada()
    {
        try {
            return $this->successResponse([
                'numeracion_unificada' => $this->service->getNumeracionUnificada(),
                'descripcion' => 'Define si la numeración de radicados es unificada o por ventanilla'
            ], 'Configuración de numeración unificada obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza numeración unificada.
     */
    public function updateNumeracionUnificada(Request $request)
    {
        try {
            $request->validate(['numeracion_unificada' => 'required|boolean']);

            $this->service->setNumeracionUnificada($request->boolean('numeracion_unificada'));

            return $this->successResponse([
                'numeracion_unificada' => $request->boolean('numeracion_unificada')
            ], 'Configuración de numeración unificada actualizada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar', $e->getMessage(), 500);
        }
    }
}