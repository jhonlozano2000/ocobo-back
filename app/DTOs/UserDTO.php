<?php

namespace App\DTOs;

use App\Models\User;

class UserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombres,
        public readonly string $apellidos,
        public readonly string $email,
        public readonly ?string $avatar_url,
        public readonly array $roles,
        public readonly ?array $cargo,
        public readonly bool $estado,
    ) {}

    public static function fromModel(User $user): self
    {
        $cargo = null;
        if ($user->cargoActivo?->cargo) {
            $cargo = [
                'id' => $user->cargoActivo->id,
                'nom_organico' => $user->cargoActivo->cargo->nom_organico,
                'cod_organico' => $user->cargoActivo->cargo->cod_organico,
            ];
        }

        return new self(
            id: $user->id,
            nombres: $user->nombres,
            apellidos: $user->apellidos,
            email: $user->email,
            avatar_url: $user->avatar_url,
            roles: $user->roles->pluck('name')->toArray(),
            cargo: $cargo,
            estado: (bool) $user->estado,
        );
    }

    public static function collection($users): array
    {
        return $users->map(fn($u) => self::fromModel($u))->toArray();
    }
}