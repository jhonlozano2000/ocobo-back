<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\StoreConfigVariasRequest;
use App\Http\Requests\Configuracion\UpdateConfigVariasRequest;
use App\Models\Configuracion\ConfigVarias;
use App\Services\Configuracion\ConfigVariasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConfigVariasController extends Controller
{
    use ApiResponseTrait;

    private const KEY_LOGO_EMPRESA = 'logo_empresa';
    private const KEY_NUMERACION_UNIFICADA = 'numeracion_unificada';

    public function __construct(
        private readonly ConfigVariasService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'search' => 'nullable|string',
                'tipo' => 'nullable|string',
                'estado' => 'nullable|boolean',
                'per_page' => 'nullable|integer',
            ]);
            $configs = $this->service->getAll($validated);
            return $this->successResponse($configs, 'Listado de configuraciones obtenido exitosamente');
        } catch (ValidationException $e) {
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado', $e->getMessage(), 500);
        }
    }

    public function store(StoreConfigVariasRequest $request): JsonResponse
    {
        try {
            $validated = $this->prepareData($request->validated(), $request);

            $config = $this->service->create($validated);
            return $this->successResponse($config, 'Configuración creada exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear', $e->getMessage(), 500);
        }
    }

    public function update(UpdateConfigVariasRequest $request, string $clave): JsonResponse
    {
        try {
            $validated = $this->prepareData(
                $request->only(['valor', 'descripcion', 'tipo', 'estado']),
                $request,
                $clave
            );

            $config = $this->service->update($clave, $validated);

            return $config
                ? $this->successResponse($config, 'Configuración actualizada exitosamente')
                : $this->errorResponse('Configuración no encontrada', null, 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar', $e->getMessage(), 500);
        }
    }

    public function updateBatch(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'configs' => 'required|array',
                'configs.*.clave' => 'required|string',
                'configs.*.valor' => 'nullable',
            ]);

            $results = $this->service->updateBatch($validated['configs']);

            return $this->successResponse($results, 'Configuraciones actualizadas exitosamente');
        } catch (ValidationException $e) {
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar configuraciones', $e->getMessage(), 500);
        }
    }

    public function getNumeracionUnificada(): JsonResponse
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

    public function updateNumeracionUnificada(Request $request): JsonResponse
    {
        try {
            $request->validate([self::KEY_NUMERACION_UNIFICADA => 'required|boolean']);

            $value = $request->boolean(self::KEY_NUMERACION_UNIFICADA);
            $this->service->setNumeracionUnificada($value);

            return $this->successResponse([self::KEY_NUMERACION_UNIFICADA => $value], 'Configuración de numeración unificada actualizada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar', $e->getMessage(), 500);
        }
    }

    private function prepareData(array $data, Request $request, ?string $clave = null): array
    {
        if (isset($data['estado'])) {
            $data['estado'] = filter_var($data['estado'], FILTER_VALIDATE_BOOLEAN);
        }

        $key = $clave ?? ($data['clave'] ?? null);
        if ($key === self::KEY_LOGO_EMPRESA) {
            $data = $this->handleLogoUpload($data, $request);
        }

        return $data;
    }

    private function handleLogoUpload(array $data, Request $request): array
    {
        $archivos = $request->allFiles();
        if (empty($archivos)) {
            return $data;
        }

        $campo = array_keys($archivos)[0];
        $nuevoLogo = ConfigVarias::guardarLogoEmpresa($request, $campo);

        if ($nuevoLogo) {
            $data['valor'] = $nuevoLogo;
        }

        return $data;
    }
}