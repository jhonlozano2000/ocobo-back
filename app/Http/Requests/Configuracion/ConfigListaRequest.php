<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigListaRequest extends FormRequest
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
            'cod' => [
                'required',
                'string',
                'max:10',
                Rule::unique('config_listas', 'cod')->ignore($this->route('lista')),
            ],
            'nombre' => 'required|string|max:70',
        ];
    }
}
