<?php

namespace App\Http\Resources\Transversal;

use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Http\Resources\Json\JsonResource;

class MisFirmasResource extends JsonResource
{
    public function toArray($request)
    {
        $tipoMap = [
            VentanillaRadicaReci::class => 'reci',
            VentanillaRadicaEnviados::class => 'enviados',
            VentanillaRadicaInterno::class => 'interno',
            VentanillaPqrs::class => 'pqrs',
        ];

        return [
            'id' => $this->id,
            'documentable_type' => $tipoMap[$this->documentable_type] ?? $this->documentable_type,
            'documentable_id' => $this->documentable_id,
            'hash_original' => $this->hash_original,
            'hash_firmado' => $this->hash_firmado,
            'estampado' => $this->estaEstampado(),
            'con_estampa_tiempo' => (bool) $this->timestamp_token,
            'fecha_firma' => $this->fecha_firma?->toIso8601String(),
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
        ];
    }
}
