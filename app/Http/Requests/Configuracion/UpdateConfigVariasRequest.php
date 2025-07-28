<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigVariasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // La autorización se maneja a través de middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'valor' => [
                'nullable',
                'string',
                'max:500'
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:500'
            ],
            'tipo' => [
                'nullable',
                'string',
                'max:50'
            ],
            'estado' => [
                'nullable',
                'in:0,1,true,false'
            ]
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar archivos si existen
            $archivos = $this->allFiles();
            foreach ($archivos as $campo => $archivo) {
                if (!$archivo->isValid()) {
                    $validator->errors()->add($campo, 'El archivo no es válido.');
                    continue;
                }

                // Validar que sea una imagen
                if (!$archivo->getMimeType() || !str_starts_with($archivo->getMimeType(), 'image/')) {
                    $validator->errors()->add($campo, 'El archivo debe ser una imagen válida.');
                    continue;
                }

                // Validar tipos permitidos
                $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!in_array($archivo->getMimeType(), $tiposPermitidos)) {
                    $validator->errors()->add($campo, 'El archivo debe ser de tipo: jpg, jpeg, png, gif.');
                    continue;
                }

                // Validar tamaño (2MB)
                if ($archivo->getSize() > 2 * 1024 * 1024) {
                    $validator->errors()->add($campo, 'El archivo no puede ser mayor a 2MB.');
                    continue;
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'valor.string' => 'El valor debe ser una cadena de texto.',
            'valor.max' => 'El valor no puede superar los 500 caracteres.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede superar los 500 caracteres.',
            'tipo.string' => 'El tipo debe ser una cadena de texto.',
            'tipo.max' => 'El tipo no puede superar los 50 caracteres.',
            'estado.in' => 'El estado debe ser 0, 1, true o false.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'valor' => 'valor',
            'descripcion' => 'descripción',
            'tipo' => 'tipo',
            'estado' => 'estado'
        ];
    }
}
