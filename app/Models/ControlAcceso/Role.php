<?php

namespace App\Models\ControlAcceso;

use App\Models\User;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Role extends SpatieRole
{
    /**
     * A role may be given various permissions.
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.permission'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.role_pivot_key'),
            config('permission.column_names.model_morph_key')
        );
    }

    /**
     * A role can be assigned to various users.
     * SOBREESCRIBIMOS ESTE MÉTODO PARA APUNTAR DIRECTAMENTE A NUESTRO MODELO USER
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            User::class, // <-- ¡AQUÍ ESTÁ LA SOLUCIÓN! Usamos nuestra clase User directamente.
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.role_pivot_key'),
            config('permission.column_names.model_morph_key')
        );
    }
}
