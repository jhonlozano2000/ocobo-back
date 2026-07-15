<?php

namespace App\Http\Resources\Transversal;

use Illuminate\Http\Resources\Json\JsonResource;

class MisFirmasResource extends JsonResource
{
    public function toArray($request)
    {
        $tipoMap = [
            'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci' => 'reci',
            'App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados' => 'enviados',
            'App\Models\VentanillaUnica\Interno\VentanillaRadicaInterno' => 'interno',
            'App\Models\VentanillaUnica\Pqrs\VentanillaPqrs' => 'pqrs',
        ];

        return [
            'id' => $this->id,
            'documentable_type' => $tipoMap[$this->documentable_type] ?? $this->documentable_type,
            'documentable_id' => $this->documentable_id,
            'hash_original' => $this->hash_original,
            'hash_firmado' => $this->hash_firmado,
            'integro' => $this->hash_original === $this->hash_firmado,
            'fecha_firma' => $this->fecha_firma?->toIso8601String(),
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
        ];
    }
}
