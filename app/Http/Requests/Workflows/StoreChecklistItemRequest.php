<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_descripcion' => 'required|string|max:500',
            'esta_completado' => 'nullable|boolean',
        ];
    }
}
