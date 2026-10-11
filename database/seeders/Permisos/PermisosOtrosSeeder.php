<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosOtrosSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'Jefe de Archivo',
            'Puede Crear Expediente',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();
        $rolJefe = Role::where('name', 'Jefe de Archivo')->first();

        $this->command->info('*  Iniciando la creación de permisos de Otros');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
            if ($rolJefe && $permiso === 'Jefe de Archivo') {
                $rolJefe->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos Otros creados satisfactoriamente');
    }
}
