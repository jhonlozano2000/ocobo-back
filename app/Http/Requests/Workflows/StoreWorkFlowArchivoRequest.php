<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNodo;
use App\Models\Workflows\WorkflowInstancia;

class StoreWorkFlowArchivoRequest extends SanitizedFormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('Workflows -> Archivos -> Subir');
    }

    public function rules(): array
    {
        return [
            'archivo' => 'required|file|max:51200|mimes:pdf,jpeg,png,gif,doc,docx,xls,xlsx,ppt,pptx,zip,rar',
            'archivable_type' => 'required|string',
            'archivable_id' => 'required|integer',
            'categoria' => 'nullable|in:adjunto,evidencia,instruccion,resultado',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $type = $this->input('archivable_type');
            $id = $this->input('archivable_id');

            $modelo = match ($type) {
                'nodo' => WorkflowNodo::class,
                'instancia' => WorkflowInstancia::class,
                'workflow', default => Workflow::class,
            };

            if (!$modelo::where('id', $id)->exists()) {
                $validator->errors()->add('archivable_id', "El {$type} especificado no existe");
            }
        });
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debe seleccionar un archivo',
            'archivo.max' => 'El archivo no puede exceder 50MB',
            'archivo.mimes' => 'El tipo de archivo no está permitido',
            'archivable_type.required' => 'Debe especificar el tipo de entidad',
            'archivable_id.required' => 'Debe especificar el ID de la entidad',
        ];
    }
}
