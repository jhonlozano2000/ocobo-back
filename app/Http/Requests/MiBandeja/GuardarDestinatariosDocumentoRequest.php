<?php

namespace App\Http\Requests\MiBandeja;

use Illuminate\Foundation\Http\FormRequest;

class GuardarDestinatariosDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->id === $this->route('documento')?->user_id;
    }

    public function rules(): array
    {
        return [
            'internos' => ['present', 'array', 'max:100'],
            'internos.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'externos' => ['present', 'array', 'max:100'],
            'externos.*' => ['required', 'integer', 'distinct', 'exists:gestion_terceros,id'],
        ];
    }

    public function messages(): array
    {
        return ['internos.*.distinct' => 'No repita funcionarios destinatarios.', 'externos.*.distinct' => 'No repita terceros destinatarios.'];
    }
}
