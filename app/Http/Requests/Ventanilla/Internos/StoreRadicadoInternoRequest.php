<?php

namespace App\Http\Requests\Ventanilla\Internos;

use App\Models\ControlAcceso\UserCargo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * M03-08/21: FormRequest para radicación interna.
 * Valida presencia de cargo activo en todos los participantes para evitar
 * asignaciones omitidas silenciosamente y resuelve la validación inline.
 */
class StoreRadicadoInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asunto' => 'required|string|max:1000',
            'clasifica_documen_id' => 'required|integer|exists:clasificacion_documental_trd,id',
            'num_folios' => 'required|integer|min:1',
            'num_anexos' => 'nullable|integer|min:0',
            'descrip_anexos' => 'nullable|string|max:500',
            'fec_venci' => 'nullable|date',
            'destinatarios' => 'required|array|min:1',
            'destinatarios.*' => 'integer|exists:users,id',
            'responsables' => 'required|array|min:1',
            'responsables.*.user_id' => 'required|integer|exists:users,id',
            'responsables.*.custodio' => 'required|boolean',
            'proyectores' => 'nullable|array',
            'proyectores.*' => 'integer|exists:users,id',
            'firmantes' => 'nullable|array',
            'firmantes.*' => 'integer|exists:users,id',
            'originadores' => 'nullable|array',
            'originadores.*' => 'integer|exists:users,id',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $responsables = $this->input('responsables', []);
            $tieneCustodio = collect($responsables)->contains(fn ($r) => ! empty($r['custodio']));
            if (! empty($responsables) && ! $tieneCustodio) {
                $validator->errors()->add('responsables', 'Al menos un responsable debe ser designado como custodio.');
            }

            // M03-08/21: Validar que todos los participantes tengan cargo activo para evitar omisiones silenciosas
            $destinatarios = (array) $this->input('destinatarios', []);
            $respUserIds = collect($responsables)->pluck('user_id')->filter()->all();
            $proyectores = (array) $this->input('proyectores', []);

            $todosUsuarios = array_unique(array_merge($destinatarios, $respUserIds, $proyectores));

            if (! empty($todosUsuarios)) {
                $cargosActivos = UserCargo::whereIn('user_id', $todosUsuarios)
                    ->where('estado', 1)
                    ->pluck('user_id')
                    ->all();

                $sinCargo = array_diff($todosUsuarios, $cargosActivos);

                if (! empty($sinCargo)) {
                    $sinCargoList = implode(', ', $sinCargo);
                    $validator->errors()->add(
                        'responsables',
                        "Los siguientes usuarios no tienen un cargo activo asignado y no pueden participar en correspondencia interna: [IDs: {$sinCargoList}]."
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'asunto.required' => 'El asunto es obligatorio.',
            'asunto.max' => 'El asunto no puede exceder 1000 caracteres.',
            'clasifica_documen_id.required' => 'Debe seleccionar una clasificación documental (TRD).',
            'clasifica_documen_id.integer' => 'La clasificación documental es inválida.',
            'clasifica_documen_id.exists' => 'La clasificación documental no existe.',
            'num_folios.required' => 'El número de folios es obligatorio.',
            'num_folios.integer' => 'El número de folios debe ser un número entero.',
            'num_folios.min' => 'El número de folios debe ser al menos 1.',
            'destinatarios.required' => 'Debe asignar al menos un destinatario.',
            'destinatarios.array' => 'Los destinatarios deben ser una lista.',
            'destinatarios.min' => 'Debe asignar al menos un destinatario.',
            'destinatarios.*.integer' => 'El destinatario es inválido.',
            'destinatarios.*.exists' => 'El destinatario no existe.',
            'responsables.required' => 'Debe asignar al menos un responsable.',
            'responsables.array' => 'Los responsables deben ser una lista.',
            'responsables.*.user_id.required' => 'El responsable es obligatorio.',
            'responsables.*.user_id.integer' => 'El responsable es inválido.',
            'responsables.*.user_id.exists' => 'El responsable no existe.',
            'responsables.*.custodio.required' => 'Debe indicar si es custodio.',
            'responsables.*.custodio.boolean' => 'El valor de custodio es inválido.',
        ];
    }
}
