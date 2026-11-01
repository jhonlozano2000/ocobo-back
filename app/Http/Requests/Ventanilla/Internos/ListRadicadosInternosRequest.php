<?php

namespace App\Http\Requests\Ventanilla\Internos;

use App\Services\VentanillaUnica\RadicadoEstadoTrabajoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRadicadosInternosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'ocr' => ['nullable', 'string', 'min:3', 'max:100'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'fecha_venci_desde' => ['nullable', 'date'],
            'fecha_venci_hasta' => ['nullable', 'date'],
            'estado_trabajo' => ['nullable', Rule::in([...RadicadoEstadoTrabajoService::getEstados(), 'ANULADO'])],
            'clasifica_documen_id' => ['nullable', 'integer', 'exists:clasificacion_documental_trd,id'],
            'con_archivos' => ['nullable', 'in:si,no'],
            'dependencia_id' => ['nullable', 'integer', 'exists:calidad_organigrama,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'El término de búsqueda no puede superar los 100 caracteres.',
            'ocr.min' => 'La búsqueda en el contenido OCR requiere al menos 3 caracteres.',
            'ocr.max' => 'La búsqueda en el contenido OCR no puede superar los 100 caracteres.',
            'fecha_desde.date' => 'La fecha desde debe tener un formato válido.',
            'fecha_hasta.date' => 'La fecha hasta debe tener un formato válido.',
            'fecha_venci_desde.date' => 'La fecha de vencimiento desde debe tener un formato válido.',
            'fecha_venci_hasta.date' => 'La fecha de vencimiento hasta debe tener un formato válido.',
            'estado_trabajo.in' => 'El estado seleccionado no es válido.',
            'clasifica_documen_id.exists' => 'La clasificación documental seleccionada no existe.',
            'con_archivos.in' => 'El filtro de archivos no es válido.',
            'dependencia_id.exists' => 'La dependencia seleccionada no existe.',
            'per_page.max' => 'El número de elementos por página no puede superar 100.',
        ];
    }
}
