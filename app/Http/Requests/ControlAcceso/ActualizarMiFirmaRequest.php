<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarMiFirmaRequest extends FormRequest
{
    public function rules(): array
    {
        return ['firma' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048', 'dimensions:max_width=4096,max_height=4096']];
    }

    public function messages(): array
    {
        return [
            'firma.required' => 'Seleccione o dibuje su firma.',
            'firma.image' => 'Seleccione una imagen PNG o JPG.',
            'firma.mimes' => 'Seleccione una imagen PNG o JPG.',
            'firma.max' => 'La firma puede ocupar hasta 2 MB.',
            'firma.dimensions' => 'La imagen puede medir hasta 4096 por 4096 pixeles.',
        ];
    }
}
