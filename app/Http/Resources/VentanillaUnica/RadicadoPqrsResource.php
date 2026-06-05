<?php

namespace App\Http\Resources\VentanillaUnica;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RadicadoPqrsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'num_radicado' => $this->num_radicado,
            'asunto' => $this->asunto,
            'fec_radicado' => $this->fec_radicado?->format('Y-m-d H:i:s'),
            'estado_trabajo' => $this->estado_trabajo,
            'tercero' => $this->whenLoaded('tercero', function () {
                return [
                    'id' => $this->tercero->id ?? null,
                    'num_documento' => $this->tercero->num_documento ?? null,
                    'nombre_completo' => $this->tercero->nombre_completo ?? null,
                ];
            }),
            'clasificacion_documental' => $this->whenLoaded('clasificacionDocumental', function () {
                return [
                    'id' => $this->clasificacionDocumental->id ?? null,
                    'nombre' => $this->clasificacionDocumental->nombre ?? null,
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}