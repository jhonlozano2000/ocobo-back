<?php

namespace App\Http\Requests\Reportes;

use App\Services\Metricas\ArchivosMetricasService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtros del tablero de métricas de archivos. Por defecto: últimos 30 días agrupados por día.
 */
class MetricasArchivosRequest extends FormRequest
{
    private const MAX_DIAS = 366;

    private const MAX_MESES = 120;

    public function authorize(): bool
    {
        return true; // Permiso 'Reporte -> Metricas de archivos' en la ruta
    }

    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'agrupacion' => ['nullable', Rule::in(['dia', 'mes'])],
            'modulo' => ['nullable', Rule::in(ArchivosMetricasService::MODULOS)],
            'dependencia_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'desde.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'agrupacion.in' => 'La agrupación debe ser por día o por mes.',
            'modulo.in' => 'El módulo no es válido.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $f = $this->filtros();
                $desde = Carbon::parse($f['desde']);
                $hasta = Carbon::parse($f['hasta']);

                if ($f['agrupacion'] === 'dia' && $desde->diffInDays($hasta) > self::MAX_DIAS) {
                    $validator->errors()->add('agrupacion', 'Para rangos de más de un año agrupe por mes.');
                }
                if ($desde->diffInMonths($hasta) > self::MAX_MESES) {
                    $validator->errors()->add('desde', 'El rango no puede superar diez años.');
                }
            },
        ];
    }

    /**
     * @return array{desde: string, hasta: string, agrupacion: string, modulo: ?string, dependencia_id: ?int}
     */
    public function filtros(): array
    {
        $hasta = $this->input('hasta') ?: now()->toDateString();

        return [
            'desde' => $this->input('desde') ?: Carbon::parse($hasta)->subDays(29)->toDateString(),
            'hasta' => $hasta,
            'agrupacion' => $this->input('agrupacion') ?: 'dia',
            'modulo' => $this->input('modulo') ?: null,
            'dependencia_id' => $this->filled('dependencia_id') ? (int) $this->input('dependencia_id') : null,
        ];
    }
}
