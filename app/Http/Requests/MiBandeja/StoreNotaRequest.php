<?php

namespace App\Http\Requests\MiBandeja;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contenido' => 'required|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'contenido.required' => 'El contenido de la nota es obligatorio.',
            'contenido.max' => 'La nota no puede superar los 5000 caracteres.',
        ];
    }
}
