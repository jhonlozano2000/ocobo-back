<?php

namespace App\Http\Resources\MiBandeja\TempReci;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso JSON para documentos colaborativos.
 *
 * Transforma el modelo Documento a formato JSON para API,
 * incluyendo relaciones cargadas.
 */
class DocumentoResource extends JsonResource
{
    /**
     * Transforma el recurso a array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'radica_reci_id' => $this->radica_reci_id,
            'titulo' => $this->titulo,
            'estado' => $this->estado,
            'notas' => $this->notas,
            'es_publico' => $this->es_publico,
            'configuracion_pagina' => $this->getConfiguracionPagina(),
            'estadisticas' => [
                'versiones' => $this->when($this->relationLoaded('versiones') || $request->routeIs('*.show'), function () {
                    return $this->versiones()->count();
                }),
                'comentarios' => $this->when($this->relationLoaded('comentarios') || $request->routeIs('*.show'), function () {
                    return $this->comentarios()->count();
                }),
                'comentarios_pendientes' => $this->when($this->relationLoaded('comentarios') || $request->routeIs('*.show'), function () {
                    return $this->comentarios()->where('resuelto', false)->count();
                }),
                'sugerencias' => [
                    'total' => $this->when($this->relationLoaded('sugerencias') || $request->routeIs('*.show'), function () {
                        return $this->sugerencias()->count();
                    }),
                    'pendientes' => $this->when($this->relationLoaded('sugerencias') || $request->routeIs('*.show'), function () {
                        return $this->sugerencias()->where('estado', 'pendiente')->count();
                    }),
                ],
                'usuarios_activos' => $this->whenLoaded('cursores', function () {
                    return $this->cursores->filter(fn($c) => $c->esActivo())->count();
                }),
            ],
            'creador' => $this->whenLoaded('creador', function () {
                $creador = $this->creador;
                return [
                    'id' => $creador->id,
                    'name' => $creador->name ?? trim($creador->nombres . ' ' . $creador->apellidos) ?: 'Usuario',
                ];
            }),
            'usuarios' => $this->whenLoaded('usuarios', fn () => 
                $this->usuarios->map(fn ($u) => [
                    'user_id' => $u->user_id,
                    'rol' => $u->rol,
                    'nombre' => $u->nombreUsuario(),
                    'color' => $u->color(),
                ])
            ),
            'contenido' => $this->whenLoaded('contenido', fn () => [
                'contenido_yjs' => $this->contenido->contenido_yjs,
                'hash' => $this->contenido->hash_contenido,
                'actualizado_por' => $this->contenido->actualizado_por,
            ]),
            'cursores' => $this->whenLoaded('cursores', fn () => 
                $this->cursores->map(fn ($c) => $c->toArray())
            ),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}