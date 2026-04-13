<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateNotificationSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_for_you' => 'nullable|array',
            'new_for_you.app' => 'nullable|boolean',
            'new_for_you.email' => 'nullable|boolean',
            'new_for_you.browser' => 'nullable|boolean',
            
            'account_activity' => 'nullable|array',
            'account_activity.app' => 'nullable|boolean',
            'account_activity.email' => 'nullable|boolean',
            'account_activity.browser' => 'nullable|boolean',
            
            'new_browser_login' => 'nullable|array',
            'new_browser_login.app' => 'nullable|boolean',
            'new_browser_login.email' => 'nullable|boolean',
            'new_browser_login.browser' => 'nullable|boolean',
            
            'new_device_linked' => 'nullable|array',
            'new_device_linked.app' => 'nullable|boolean',
            'new_device_linked.email' => 'nullable|boolean',
            'new_device_linked.browser' => 'nullable|boolean',
            
            'email_notifications' => 'nullable|array',
            'email_notifications.app' => 'nullable|boolean',
            'email_notifications.email' => 'nullable|boolean',
            'email_notifications.browser' => 'nullable|boolean',
            
            // Legacy format support
            'new_for_you_boolean' => 'nullable|boolean',
            'account_activity_boolean' => 'nullable|boolean',
            'new_browser_login_boolean' => 'nullable|boolean',
            'new_device_linked_boolean' => 'nullable|boolean',
            'email_notifications_boolean' => 'nullable|boolean',
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
            'new_for_you.array' => 'El formato de notificaciones nuevas es inválido.',
            'new_for_you.*.boolean' => 'Los valores deben ser verdadero o falso.',
            'account_activity.array' => 'El formato de actividad de cuenta es inválido.',
            'new_browser_login.array' => 'El formato de nuevos inicios de sesión es inválido.',
            'new_device_linked.array' => 'El formato de nuevos dispositivos es inválido.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'Datos de validación incorrectos',
            'errors' => $validator->errors()
        ], 422));
    }
}