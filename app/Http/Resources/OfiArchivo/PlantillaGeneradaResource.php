<?php

namespace App\Http\Resources\OfiArchivo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plantilla generada para los listados (sin el contenido, que solo hace falta al crear o editar).
 */
class PlantillaGeneradaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo ? ['id' => $this->tipo->id, 'nombre' => $this->tipo->nombre] : null,
            'institucional' => $this->institucional,
            'activo' => $this->activo,
            'propia' => $this->user_id === $request->user()?->id,
            'creador' => $this->whenLoaded('creador', fn () => $this->creador
                ? trim($this->creador->nombres.' '.$this->creador->apellidos)
                : null),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
