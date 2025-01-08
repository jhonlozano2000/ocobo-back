<?php

namespace App\Http\Requests\Configuracion;

use App\Models\Configuracion\ConfigLista;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigListaDetalleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        $detalleId = $this->route('lista_detalle'); // ID del detalle actual

        return [
            'lista_id' => 'required|exists:config_listas,id',
            'nombre' => [
                'required',
                'string',
                'max:70',
                Rule::unique('config_listas_detalles', 'nombre')
                    ->where('lista_id', $this->lista_id) // Validar dentro de la misma lista
                    ->ignore($detalleId),               // Excluir el ID actual
            ],
        ];
    }

    public function messages()
    {
        $lista = ConfigLista::find($this->lista_id);
        $nombreLista = $lista ? $lista->nombre : 'desconocida';

        return [
            'lista_id.required' => 'El ID de la lista es obligatorio.',
            'lista_id.exists' => 'El ID de la lista no es válido.',

            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar los 70 caracteres.',
            'nombre.unique' => 'El nombre ya existe en la lista "' . $nombreLista . '", por favor elija otro.',
        ];
    }
}
