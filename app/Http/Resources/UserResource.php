<?php

namespace App\Http\Resources;

use App\Http\Resources\Traits\SanitizesApiOutput;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    use SanitizesApiOutput;

    protected array $sensitiveFields = ['password', 'token', 'api_key', 'secret'];
    protected array $maskedFields = ['num_docu' => 'last_4', 'email' => 'mask', 'tel' => 'last_4', 'movil' => 'last_4'];

    public function toArray($request)
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->load(['roles.permissions', 'permissions', 'cargoActivo.cargo']);

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

            $jerarquia = $this->cargoActivo->cargo->getJerarquiaCompleta();
            $cargoIndex = -1;

            foreach ($jerarquia as $index => $nivel) {
                if ($nivel['id'] === $this->cargoActivo->cargo->id && $nivel['tipo'] === 'Cargo') {
                    $cargoIndex = $index;
                    break;
                }
            }

            if ($cargoIndex > 0) {
                $parentDirecto = $jerarquia[$cargoIndex - 1];

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

        $data = [
            'id' => $this->id,
            'num_docu' => $this->num_docu,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'email' => $this->email,
            'tel' => $this->tel,
            'movil' => $this->movil,
            'dir' => $this->dir,
            'estado' => $this->estado,
            'firma' => $this->firma,
            'avatar' => $this->avatar,
            'firma_url' => $this->firma_url,
            'avatar_url' => $this->avatar_url,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->sortBy('name')->pluck('name')->values()->toArray(),
            'cargo' => $cargo,
            'oficina' => $oficina,
            'dependencia' => $dependencia,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        return $this->sanitizeOutput($data);
    }
}