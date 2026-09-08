<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosOtrosPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'Mi Bandeja -> Jefe de dependencia',
            'Mi Bandeja -> Jefe de oficina',
            'Mi Bandeja -> Puede firmar',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();

        $this->command->info('*   Iniciando la creación de permisos de jerarquía Mi Bandeja');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);

            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos de jerarquía Mi Bandeja creados satisfactoriamente');
    }
}
