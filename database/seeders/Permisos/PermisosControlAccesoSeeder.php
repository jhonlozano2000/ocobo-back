<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosControlAccesoSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'Control de acceso - Usuarios -> Listar',
            'Control de acceso - Usuarios -> Crear',
            'Control de acceso - Usuarios -> Editar',
            'Control de acceso - Usuarios -> Mostrar',
            'Control de acceso - Usuarios -> Eliminar',
            'Control de acceso - Usuarios -> Exportar',
            'Control de acceso - Roles -> Listar',
            'Control de acceso - Roles -> Crear',
            'Control de acceso - Roles -> Editar',
            'Control de acceso - Roles -> Mostrar',
            'Control de acceso - Roles -> Eliminar',
            'Control de acceso - Roles -> Exportar',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();

        $this->command->info('*   Iniciando la creación de permisos de Control de Acceso');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos Control de Acceso creados satisfactoriamente');
    }
}
