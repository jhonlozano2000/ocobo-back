<?php

namespace App\Http\Requests\Configuracion;

use App\Models\Configuracion\ConfigServerArchivo;
use Illuminate\Foundation\Http\FormRequest;

class ConfigServerArchivoRequest extends FormRequest
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
        $serverId = $this->route('config_server_archivo');

        return [
            'proceso_id' => 'required|exists:config_listas_detalles,id',
            'host' => 'required|string|max:11',
            'ruta' => 'nullable|string|max:100',
            'user' => 'required|string|max:20',
            'password' => $serverId ? 'nullable|string|max:20' : 'required|string|max:20',
            'detalle' => 'nullable|string|max:200',
            'estado' => [
                'required',
                'boolean',
                function ($attribute, $value, $fail) use ($serverId) {
                    if ($value == 1) { // Si intenta activar este servidor
                        $existeActivo = ConfigServerArchivo::where('proceso_id', $this->proceso_id)
                            ->where('estado', 1)
                            ->where('id', '!=', $serverId) // Excluir el ID actual en caso de update
                            ->exists();

                        if ($existeActivo) {
                            $fail("Ya existe un servidor activo para este proceso. Solo puede haber uno con estado activo.");
                        }
                    }
                },
            ],
        ];
    }

    public function messages()
    {
        return [
            'proceso_id.required' => 'El proceso es obligatorio.',
            'proceso_id.exists' => 'El proceso seleccionado no es válido.',
            'host.required' => 'El host es obligatorio.',
            'host.string' => 'El host debe ser una cadena de texto.',
            'host.max' => 'El host no puede superar los 11 caracteres.',
            'ruta.string' => 'La ruta debe ser una cadena de texto.',
            'ruta.max' => 'La ruta no puede superar los 100 caracteres.',
            'user.required' => 'El usuario es obligatorio.',
            'user.string' => 'El usuario debe ser una cadena de texto.',
            'user.max' => 'El usuario no puede superar los 20 caracteres.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser una cadena de texto.',
            'password.max' => 'La contraseña no puede superar los 20 caracteres.',
            'detalle.string' => 'El detalle debe ser una cadena de texto.',
            'detalle.max' => 'El detalle no puede superar los 200 caracteres.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.boolean' => 'El estado debe ser 0 o 1.',
        ];
    }
}
