<?php

namespace App\DTOs;

use App\Models\Calidad\CalidadOrganigrama;

class OrganigramaDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $tipo,
        public readonly string $nom_organico,
        public readonly string $cod_organico,
        public readonly ?int $parent,
        public readonly ?string $observaciones,
        public readonly array $children,
    ) {}

    public static function fromModel(CalidadOrganigrama $organigrama): self
    {
        return new self(
            id: $organigrama->id,
            tipo: $organigrama->tipo,
            nom_organico: $organigrama->nom_organico,
            cod_organico: $organigrama->cod_organico,
            parent: $organigrama->parent,
            observaciones: $organigrama->observaciones,
            children: $organigrama->children->map(fn($c) => self::fromModel($c))->toArray(),
        );
    }

    public static function collection($organigramas): array
    {
        return $organigramas->map(fn($o) => self::fromModel($o))->toArray();
    }
}