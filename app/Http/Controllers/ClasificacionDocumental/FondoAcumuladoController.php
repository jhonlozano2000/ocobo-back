<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ClasificacionDocumental\FondoAcumulado;
use App\Models\ClasificacionDocumental\FondoAcumuladoPeriodo;
use App\Models\ClasificacionDocumental\FondoAcumuladoUnidad;
use App\Services\ClasificacionDocumental\TVDService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fondos acumulados, sus períodos históricos y las unidades administrativas de cada período
 * (M11-02). Son el marco sobre el que se registra y aprueba la TVD.
 */
class FondoAcumuladoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TVDService $tvdService
    ) {}

    // ===================== FONDOS =====================

    public function index(): JsonResponse
    {
        $fondos = FondoAcumulado::with(['periodos' => fn ($q) => $q->withCount(['unidades', 'tvd'])])
            ->orderBy('codigo')
            ->get();

        return $this->successResponse($fondos, 'Fondos acumulados obtenidos exitosamente');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->reglasFondo());
        $data['user_register'] = auth()->id();

        return $this->successResponse(FondoAcumulado::create($data), 'Fondo acumulado creado exitosamente', 201);
    }

    public function update(Request $request, FondoAcumulado $fondo): JsonResponse
    {
        $data = $request->validate($this->reglasFondo($fondo));

        // Los períodos ya registrados deben seguir dentro de las fechas extremas del fondo
        $inicio = array_key_exists('fecha_inicio', $data) ? $data['fecha_inicio'] : $fondo->fecha_inicio?->toDateString();
        $fin = array_key_exists('fecha_fin', $data) ? $data['fecha_fin'] : $fondo->fecha_fin?->toDateString();
        $fuera = ($inicio || $fin) && $fondo->periodos()
            ->where(function ($q) use ($inicio, $fin) {
                if ($inicio) {
                    $q->orWhere('fecha_inicio', '<', $inicio);
                }
                if ($fin) {
                    $q->orWhere('fecha_fin', '>', $fin);
                }
            })
            ->exists();

        if ($fuera) {
            throw ValidationException::withMessages(['fecha_inicio' => 'Hay períodos fuera de las nuevas fechas extremas del fondo.']);
        }

        $fondo->update($data);

        return $this->successResponse($fondo->fresh(), 'Fondo acumulado actualizado exitosamente');
    }

    public function destroy(FondoAcumulado $fondo): JsonResponse
    {
        if ($fondo->periodos()->where(fn ($q) => $q->whereNotNull('fecha_aprobacion')->orWhereHas('tvd'))->exists()) {
            return $this->warningResponse('No se puede eliminar el fondo: tiene períodos aprobados o con TVD registrada');
        }

        $fondo->delete();

        return $this->successResponse(null, 'Fondo acumulado eliminado exitosamente');
    }

    // ===================== PERÍODOS =====================

    public function storePeriodo(Request $request, FondoAcumulado $fondo): JsonResponse
    {
        $data = $request->validate($this->reglasPeriodo());
        $this->validarFechasPeriodo($fondo, $data['fecha_inicio'], $data['fecha_fin']);

        $periodo = $fondo->periodos()->create($data + ['user_register' => auth()->id()]);

        return $this->successResponse($periodo, 'Período creado exitosamente', 201);
    }

    public function updatePeriodo(Request $request, FondoAcumuladoPeriodo $periodo): JsonResponse
    {
        $data = $request->validate($this->reglasPeriodo(true));

        return DB::transaction(function () use ($periodo, $data) {
            $this->tvdService->asegurarEditable($periodo->id);

            $this->validarFechasPeriodo(
                $periodo->fondo,
                $data['fecha_inicio'] ?? $periodo->fecha_inicio->toDateString(),
                $data['fecha_fin'] ?? $periodo->fecha_fin->toDateString(),
                $periodo->id
            );

            $periodo->update($data);

            return $this->successResponse($periodo->fresh(), 'Período actualizado exitosamente');
        });
    }

    public function destroyPeriodo(FondoAcumuladoPeriodo $periodo): JsonResponse
    {
        return DB::transaction(function () use ($periodo) {
            $this->tvdService->asegurarEditable($periodo->id);

            if ($periodo->tvd()->exists()) {
                return $this->warningResponse('No se puede eliminar el período: tiene TVD registrada');
            }

            $periodo->delete();

            return $this->successResponse(null, 'Período eliminado exitosamente');
        });
    }

    // ===================== UNIDADES =====================

    public function unidades(FondoAcumuladoPeriodo $periodo): JsonResponse
    {
        $unidades = $periodo->unidades()->withCount('tvd')->get();

        return $this->successResponse($unidades, 'Unidades administrativas obtenidas exitosamente');
    }

    public function storeUnidad(Request $request, FondoAcumuladoPeriodo $periodo): JsonResponse
    {
        $data = $request->validate($this->reglasUnidad($periodo));

        return DB::transaction(function () use ($periodo, $data) {
            $this->tvdService->asegurarEditable($periodo->id);

            return $this->successResponse($periodo->unidades()->create($data), 'Unidad administrativa creada exitosamente', 201);
        });
    }

    public function updateUnidad(Request $request, FondoAcumuladoUnidad $unidad): JsonResponse
    {
        $data = $request->validate($this->reglasUnidad($unidad->periodo, $unidad));

        if (! empty($data['parent']) && $this->esDescendiente((int) $data['parent'], $unidad->id)) {
            throw ValidationException::withMessages(['parent' => 'La unidad no puede depender de sí misma ni de una unidad subordinada.']);
        }

        return DB::transaction(function () use ($unidad, $data) {
            $this->tvdService->asegurarEditable($unidad->periodo_id);

            $unidad->update($data);

            return $this->successResponse($unidad->fresh(), 'Unidad administrativa actualizada exitosamente');
        });
    }

    public function destroyUnidad(FondoAcumuladoUnidad $unidad): JsonResponse
    {
        return DB::transaction(function () use ($unidad) {
            $this->tvdService->asegurarEditable($unidad->periodo_id);

            if ($unidad->tvd()->exists()) {
                return $this->warningResponse('No se puede eliminar la unidad: tiene TVD registrada');
            }
            if ($unidad->children()->exists()) {
                return $this->warningResponse('No se puede eliminar la unidad: tiene unidades subordinadas');
            }

            $unidad->delete();

            return $this->successResponse(null, 'Unidad administrativa eliminada exitosamente');
        });
    }

    // ===================== VALIDACIÓN =====================

    private function reglasFondo(?FondoAcumulado $fondo = null): array
    {
        $req = $fondo ? 'sometimes' : 'required';

        return [
            'codigo' => [$req, 'string', 'max:30', Rule::unique('fondos_acumulados', 'codigo')->ignore($fondo?->id)],
            'nombre' => [$req, 'string', 'max:255'],
            'entidad_productora' => [$req, 'string', 'max:255'],
            'origen' => [$req, Rule::in(FondoAcumulado::ORIGENES)],
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'descripcion' => ['nullable', 'string'],
        ];
    }

    private function reglasPeriodo(bool $edicion = false): array
    {
        $req = $edicion ? 'sometimes' : 'required';

        return [
            'nombre' => [$req, 'string', 'max:255'],
            'fecha_inicio' => [$req, 'date_format:Y-m-d'],
            'fecha_fin' => [$req, 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'acto_administrativo' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
        ];
    }

    private function reglasUnidad(FondoAcumuladoPeriodo $periodo, ?FondoAcumuladoUnidad $unidad = null): array
    {
        $req = $unidad ? 'sometimes' : 'required';

        return [
            'codigo' => [
                $req, 'string', 'max:20',
                Rule::unique('fondo_acumulado_unidades', 'codigo')->where('periodo_id', $periodo->id)->ignore($unidad?->id),
            ],
            'nombre' => [$req, 'string', 'max:255'],
            // La unidad superior debe ser del mismo período
            'parent' => ['nullable', 'integer', Rule::exists('fondo_acumulado_unidades', 'id')->where('periodo_id', $periodo->id)],
        ];
    }

    /**
     * El período cae dentro de las fechas extremas del fondo y no se cruza con otro período suyo.
     */
    private function validarFechasPeriodo(FondoAcumulado $fondo, string $inicio, string $fin, ?int $ignorar = null): void
    {
        if ($inicio > $fin) {
            throw ValidationException::withMessages(['fecha_fin' => 'La fecha final debe ser posterior o igual a la inicial.']);
        }

        if (($fondo->fecha_inicio && $inicio < $fondo->fecha_inicio->toDateString())
            || ($fondo->fecha_fin && $fin > $fondo->fecha_fin->toDateString())) {
            throw ValidationException::withMessages(['fecha_inicio' => 'El período debe estar dentro de las fechas extremas del fondo.']);
        }

        $cruce = $fondo->periodos()
            ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar))
            ->where('fecha_inicio', '<=', $fin)
            ->where('fecha_fin', '>=', $inicio)
            ->first();

        if ($cruce) {
            throw ValidationException::withMessages(['fecha_inicio' => 'El período se cruza con «'.$cruce->nombre.'».']);
        }
    }

    private function esDescendiente(int $candidato, int $unidadId): bool
    {
        $actual = FondoAcumuladoUnidad::find($candidato);

        while ($actual) {
            if ((int) $actual->id === $unidadId) {
                return true;
            }

            $actual = $actual->parent ? FondoAcumuladoUnidad::find($actual->parent) : null;
        }

        return false;
    }
}
