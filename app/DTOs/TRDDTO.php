<?php

namespace App\DTOs;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;

class TRDDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $tipo,
        public readonly ?string $cod,
        public readonly string $nom,
        public readonly ?int $parent,
        public readonly int $dependencia_id,
        public readonly ?string $a_g,
        public readonly ?string $a_c,
        public readonly bool $ct,
        public readonly bool $e,
        public readonly bool $m_d,
        public readonly bool $s,
        public readonly ?string $procedimiento,
        public readonly bool $estado,
    ) {}

    public static function fromModel(ClasificacionDocumentalTRD $trd): self
    {
        return new self(
            id: $trd->id,
            tipo: $trd->tipo,
            cod: $trd->cod,
            nom: $trd->nom,
            parent: $trd->parent,
            dependencia_id: $trd->dependencia_id,
            a_g: $trd->a_g,
            a_c: $trd->a_c,
            ct: (bool) $trd->ct,
            e: (bool) $trd->e,
            m_d: (bool) $trd->m_d,
            s: (bool) $trd->s,
            procedimiento: $trd->procedimiento,
            estado: (bool) $trd->estado,
        );
    }

    public static function collection($trds): array
    {
        return $trds->map(fn($t) => self::fromModel($t))->toArray();
    }
}