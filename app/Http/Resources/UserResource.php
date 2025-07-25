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
        $this->load(['roles.permissions', 'permissions']);

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
            'permissions'   => $this->getAllPermissions()->pluck('name')->toArray(),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}

