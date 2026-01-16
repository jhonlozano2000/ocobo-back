<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Limpiar cache de permisos y recargar relaciones
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->load(['roles.permissions', 'permissions', 'cargoActivo.cargo']);

        // Obtener información de dependencia y oficina
        $oficina = null;
        $dependencia = null;
        $cargo = null;

        if ($this->cargoActivo && $this->cargoActivo->cargo) {
            $cargo = [
                'id' => $this->cargoActivo->cargo->id,
                'nom_organico' => $this->cargoActivo->cargo->nom_organico,
                'cod_organico' => $this->cargoActivo->cargo->cod_organico,
                'tipo' => $this->cargoActivo->cargo->tipo,
                'fecha_inicio' => $this->cargoActivo->fecha_inicio?->format('Y-m-d'),
                'observaciones' => $this->cargoActivo->observaciones
            ];

            // Usar el método existente getJerarquiaCompleta() para obtener la jerarquía
            $jerarquia = $this->cargoActivo->cargo->getJerarquiaCompleta();

            // Buscar la dependencia y oficina directamente relacionadas al cargo
            // (no la primera en toda la jerarquía, sino la más cercana al cargo)
            $cargoIndex = -1;

            // Encontrar la posición del cargo en la jerarquía
            foreach ($jerarquia as $index => $nivel) {
                if ($nivel['id'] === $this->cargoActivo->cargo->id && $nivel['tipo'] === 'Cargo') {
                    $cargoIndex = $index;
                    break;
                }
            }

            // Si encontramos el cargo, buscar su dependencia/oficina padre directa
            if ($cargoIndex > 0) {
                $parentDirecto = $jerarquia[$cargoIndex - 1]; // El elemento anterior es el padre directo

                if ($parentDirecto['tipo'] === 'Oficina') {
                    $oficina = [
                        'id' => $parentDirecto['id'],
                        'nom_organico' => $parentDirecto['nom_organico'],
                        'cod_organico' => $parentDirecto['cod_organico'],
                        'tipo' => $parentDirecto['tipo']
                    ];
                } elseif ($parentDirecto['tipo'] === 'Dependencia') {
                    $dependencia = [
                        'id' => $parentDirecto['id'],
                        'nom_organico' => $parentDirecto['nom_organico'],
                        'cod_organico' => $parentDirecto['cod_organico'],
                        'tipo' => $parentDirecto['tipo']
                    ];
                }
            }
        }

        return [
            'id'            => $this->id,
            'num_docu'      => $this->num_docu,
            'nombres'       => $this->nombres,
            'apellidos'     => $this->apellidos,
            'email'         => $this->email,
            'tel'           => $this->tel,
            'movil'         => $this->movil,
            'dir'           => $this->dir,
            'estado'        => $this->estado,
            'firma'         => $this->firma,
            'avatar'        => $this->avatar,
            'firma_url'     => $this->firma_url,
            'avatar_url'    => $this->avatar_url,
            'roles'         => $this->getRoleNames(),
            'permissions'   => $this->getAllPermissions()->sortBy('name')->pluck('name')->values()->toArray(),
            'cargo'         => $cargo,
            'oficina'       => $oficina,
            'dependencia'   => $dependencia,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
