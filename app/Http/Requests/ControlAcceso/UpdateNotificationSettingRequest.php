<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingRequest extends FormRequest
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
            'new_for_you' => [
                'required',
                'boolean'
            ],
            'account_activity' => [
                'required',
                'boolean'
            ],
            'new_browser_login' => [
                'required',
                'boolean'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'new_for_you.required' => 'La configuración de notificaciones nuevas es obligatoria.',
            'new_for_you.boolean' => 'La configuración de notificaciones nuevas debe ser verdadera o falsa.',
            'account_activity.required' => 'La configuración de actividad de cuenta es obligatoria.',
            'account_activity.boolean' => 'La configuración de actividad de cuenta debe ser verdadera o falsa.',
            'new_browser_login.required' => 'La configuración de nuevos inicios de sesión es obligatoria.',
            'new_browser_login.boolean' => 'La configuración de nuevos inicios de sesión debe ser verdadera o falsa.',
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
            'new_for_you' => 'notificaciones nuevas',
            'account_activity' => 'actividad de cuenta',
            'new_browser_login' => 'nuevos inicios de sesión'
        ];
    }
}
