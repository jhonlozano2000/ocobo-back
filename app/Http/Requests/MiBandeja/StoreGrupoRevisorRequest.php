<?php

namespace App\Http\Requests\MiBandeja;

use Illuminate\Foundation\Http\FormRequest;

class StoreGrupoRevisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'cargo_id' => 'nullable|integer|exists:calidad_organigrama,id',
        ];
    }
}
