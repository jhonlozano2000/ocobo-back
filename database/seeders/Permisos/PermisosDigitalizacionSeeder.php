<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosDigitalizacionSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'Digitalización -> Listar',
            'Digitalización -> Crear',
            'Digitalización -> Editar',
            'Digitalización -> Mostrar',
            'Digitalización -> Eliminar',
            'Digitalización -> Exportar',
            'Mi Espacio -> Comunicaciones',
            'Mi Espacio -> Mi Disco',
            'Mi Espacio -> Archivos digitalizados',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();
        $rolDigitalizador = Role::where('name', 'Digitalizador')->first();
        $rolMiEspacio = Role::where('name', 'Mi espacion')->first();

        $this->command->info('*  Iniciando la creación de permisos de Digitalizacón');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
            if ($rolDigitalizador) {
                $rolDigitalizador->givePermissionTo($permission);
            }
            if ($rolMiEspacio) {
                $rolMiEspacio->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos Digitalizacón creados satisfactoriamente');
    }
}
