<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigListaDetalleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lista_id' => 'required|exists:config_listas,id',
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('config_listas_detalles', 'codigo')->ignore($this->route('lista_detalle')),
            ],
            'nombre' => 'required|string|max:70',
        ];
    }
}
