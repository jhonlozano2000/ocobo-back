<?php

namespace App\Http\Requests\Workflows;

use App\Http\Requests\SanitizedFormRequest;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkflowNodo;

class StoreWorkFlowArchivoRequest extends SanitizedFormRequest
{
    /** Alias que envía el cliente → clase que se guarda en archivable_type. */
    public const TIPOS = [
        'nodo' => WorkflowNodo::class,
        'instancia' => WorkflowInstancia::class,
        'workflow' => Workflow::class,
    ];

    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('Workflows -> Archivos -> Subir');
    }

    public function rules(): array
    {
        return [
            'archivo' => 'required|file|max:51200|mimes:pdf,jpeg,png,gif,doc,docx,xls,xlsx,ppt,pptx,zip,rar',
            'archivable_type' => 'required|string|in:'.implode(',', array_keys(self::TIPOS)),
            'archivable_id' => 'required|integer',
            'categoria' => 'nullable|in:adjunto,evidencia,instruccion,resultado',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $type = $this->input('archivable_type');
            $id = $this->input('archivable_id');

            if (! $type || ! $id || ! isset(self::TIPOS[$type])) {
                return;
            }

            // El {workflow} de la ruta manda: el archivable debe pertenecerle
            $workflowId = (int) $this->route('workflow');

            $query = self::TIPOS[$type]::where('id', $id);

            if ($type !== 'workflow') {
                $query->where('workflow_id', $workflowId);
            } elseif ((int) $id !== $workflowId) {
                $validator->errors()->add('archivable_id', 'El archivo pertenece a otro workflow');

                return;
            }

            // M19-52: a una instancia solo adjunta quien puede verla; se responde igual que si no existiera
            if ($type === 'instancia') {
                $query->visiblesPara($this->user());
            }

            if (! $query->exists()) {
                $validator->errors()->add('archivable_id', "El {$type} especificado no existe en este workflow");
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
            'archivable_type.in' => 'El tipo de entidad no es válido',
            'archivable_id.required' => 'Debe especificar el ID de la entidad',
        ];
    }
}
