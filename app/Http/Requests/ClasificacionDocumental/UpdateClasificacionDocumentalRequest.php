<?php

namespace App\Http\Requests\ClasificacionDocumental;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClasificacionDocumentalRequest extends FormRequest
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
        return [
            'nom' => 'required|string|max:100',
            'cod' => 'nullable|string|max:10',
            'a_g' => 'nullable|string|max:5',
            'a_c' => 'nullable|string|max:5',
            'ct' => 'nullable|boolean',
            'e' => 'nullable|boolean',
            'm_d' => 'nullable|boolean',
            's' => 'nullable|boolean',
            'procedimiento' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'nom.required' => 'El nombre es obligatorio.',
            'nom.max' => 'El nombre no debe superar los 100 caracteres.',
            'cod.max' => 'El código no debe superar los 10 caracteres.',
        ];
    }
}
