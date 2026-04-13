<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Configuracion\ConfigCalendarioFestivo;
use App\Services\Configuracion\BusinessDaysService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConfigCalendarioFestivoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly BusinessDaysService $businessDaysService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $anio = $request->get('anio', now()->year);
            $festivos = ConfigCalendarioFestivo::where('anio', $anio)
                ->orWhereRaw('YEAR(fecha) = ?', [$anio])
                ->orderBy('fecha', 'asc')
                ->get();

            return $this->successResponse($festivos, 'Festivos obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los días festivos', $e->getMessage(), 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fecha' => 'required|date|unique:config_calendario_festivos,fecha',
                'nombre' => 'required|string|max:150',
                'tipo' => 'nullable|string|in:' . implode(',', ConfigCalendarioFestivo::getTipos()),
            ]);

            $validated['anio'] = \Carbon\Carbon::parse($validated['fecha'])->year;

            $festivo = ConfigCalendarioFestivo::create($validated);
            $this->businessDaysService->clearCache($validated['anio']);

            return $this->successResponse($festivo, 'Día no hábil creado exitosamente', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Datos inválidos', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear el día no hábil', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $festivo = ConfigCalendarioFestivo::find($id);

            if (!$festivo) {
                return $this->errorResponse('Día no hábil no encontrado', null, 404);
            }

            $validated = $request->validate([
                'fecha' => 'sometimes|date|unique:config_calendario_festivos,fecha,' . $id,
                'nombre' => 'sometimes|string|max:150',
                'tipo' => 'nullable|string|in:' . implode(',', ConfigCalendarioFestivo::getTipos()),
            ]);

            if (isset($validated['fecha'])) {
                $validated['anio'] = \Carbon\Carbon::parse($validated['fecha'])->year;
            }

            $festivo->update($validated);
            $this->businessDaysService->clearCache();

            return $this->successResponse($festivo->fresh(), 'Día no hábil actualizado exitosamente');
        } catch (ValidationException $e) {
            return $this->errorResponse('Datos inválidos', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el día no hábil', $e->getMessage(), 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $festivo = ConfigCalendarioFestivo::find($id);

            if (!$festivo) {
                return $this->errorResponse('Día no hábil no encontrado', null, 404);
            }

            $anio = $festivo->anio;
            $festivo->delete();
            $this->businessDaysService->clearCache($anio);

            return $this->successResponse(null, 'Día no hábil eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el día no hábil', $e->getMessage(), 500);
        }
    }

    public function verificarFecha(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['fecha' => 'required|date']);

            $existe = ConfigCalendarioFestivo::where('fecha', $validated['fecha'])->exists();
            $festivo = $existe ? ConfigCalendarioFestivo::where('fecha', $validated['fecha'])->first() : null;

            return $this->successResponse([
                'es_festivo' => $existe,
                'fecha' => $validated['fecha'],
                'festivo' => $festivo,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al verificar la fecha', $e->getMessage(), 500);
        }
    }

    public function festivosPorAnio(int $anio): JsonResponse
    {
        try {
            $festivos = $this->businessDaysService->getFestivos($anio);

            return $this->successResponse($festivos, "Festivos del año {$anio} obtenidos exitosamente");
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los festivos del año', $e->getMessage(), 500);
        }
    }

    public function calcularVencimiento(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fecha_inicio' => 'required|date',
                'dias_habiles' => 'required|integer|min:1|max:365',
            ]);

            $fechaVencimiento = $this->businessDaysService->calcularVencimiento(
                \Carbon\Carbon::parse($validated['fecha_inicio']),
                (int) $validated['dias_habiles']
            );

            return $this->successResponse([
                'fecha_inicio' => $validated['fecha_inicio'],
                'dias_habiles' => (int) $validated['dias_habiles'],
                'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d'),
            ], 'Vencimiento calculado exitosamente');
        } catch (ValidationException $e) {
            return $this->errorResponse('Datos inválidos', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al calcular vencimiento', $e->getMessage(), 500);
        }
    }

    public function importar(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'festivos' => 'required|array|min:1',
                'festivos.*.fecha' => 'required|date',
                'festivos.*.nombre' => 'required|string|max:150',
                'festivos.*.tipo' => 'nullable|string|in:' . implode(',', ConfigCalendarioFestivo::getTipos()),
            ]);

            $resultados = $this->businessDaysService->importarFestivos($validated['festivos']);

            return $this->successResponse($resultados, 'Festivos importados exitosamente');
        } catch (ValidationException $e) {
            return $this->errorResponse('Datos inválidos', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al importar festivos', $e->getMessage(), 500);
        }
    }

    public function generarFestivosColombia(int $anio): JsonResponse
    {
        try {
            $festivosExistentes = ConfigCalendarioFestivo::where('anio', $anio)->count();

            if ($festivosExistentes > 0) {
                return $this->successResponse([
                    'ya_existen' => true,
                    'cantidad' => $festivosExistentes,
                    'mensaje' => "Ya existen {$festivosExistentes} días no hábiles configurados para el año {$anio}"
                ], 'Los festivos ya están configurados para este año');
            }

            $resultados = $this->businessDaysService->generarFestivosColombia($anio);

            return $this->successResponse([
                'ya_existen' => false,
                'creados' => $resultados['creados'],
                'omitidos' => $resultados['omitidos'],
                'errores' => $resultados['errores'],
            ], "Festivos de Colombia para {$anio} generados exitosamente");
        } catch (\Exception $e) {
            return $this->errorResponse('Error al generar festivos', $e->getMessage(), 500);
        }
    }

    public function clearCache(): JsonResponse
    {
        try {
            $this->businessDaysService->clearCache();

            return $this->successResponse(null, 'Caché de festivos limpiada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al limpiar caché', $e->getMessage(), 500);
        }
    }
}