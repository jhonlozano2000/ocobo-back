<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosCalidadSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'Calidad - Organigrama -> Listar',
            'Calidad - Organigrama -> Crear',
            'Calidad - Organigrama -> Editar',
            'Calidad - Organigrama -> Mostrar',
            'Calidad - Organigrama -> Eliminar',
            'Calidad - Organigrama -> Exportar',
            'Calidad -> Crear procesos',
            'Calidad -> Crear Macro Procesos',
            'Calidad -> Crear Procedimientos',
            'Calidad -> Crear Tipos de Archivos',
            'Calidad -> Cargar Archivos',
            'Calidad -> Exportar',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();
        $rolRadicadorPQRSF = Role::where('name', 'Radicador PQRSF')->first();

        $this->command->info('*  Iniciando la creación de permisos de Calidad');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
            if ($rolRadicadorPQRSF) {
                $rolRadicadorPQRSF->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos Calidad creados satisfactoriamente');
    }
}
