<?php

namespace App\Http\Requests\ControlAcceso;

use App\Models\ControlAcceso\UserNotificationSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateNotificationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Una entrada por categoría con sus canales: {"new_for_you": {"app": true, "email": false, ...}, ...}
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = [];
        foreach (UserNotificationSetting::CATEGORIAS as $categoria => $canales) {
            $reglas[$categoria] = 'nullable|array';
            foreach (array_keys($canales) as $canal) {
                $reglas["{$categoria}.{$canal}"] = 'nullable|boolean';
            }
        }

        return $reglas;
    }

    public function messages(): array
    {
        return [
            '*.array' => 'El formato de la preferencia es inválido.',
            '*.*.boolean' => 'Los valores deben ser verdadero o falso.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'Datos de validación incorrectos',
            'errors' => $validator->errors(),
        ], 422));
    }
}
