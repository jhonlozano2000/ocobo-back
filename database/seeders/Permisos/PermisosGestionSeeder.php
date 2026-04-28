<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosGestionSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'Gestión - Terceros -> Listar',
            'Gestión - Terceros -> Crear',
            'Gestión - Terceros -> Editar',
            'Gestión - Terceros -> Mostrar',
            'Gestión - Terceros -> Eliminar',
            'Gestión - Terceros -> Exportar',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();
        $rolDigitalizador = Role::where('name', 'Digitalizador')->first();

        $this->command->info('*  Iniciando la creación de permisos de Gestión');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
            if ($rolDigitalizador) {
                $rolDigitalizador->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos Gestión creados satisfactoriamente');
    }
}
