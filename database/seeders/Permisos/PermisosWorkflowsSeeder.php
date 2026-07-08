<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosWorkflowsSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'Workflows -> Workflows -> Listar',
            'Workflows -> Workflows -> Crear',
            'Workflows -> Workflows -> Mostrar',
            'Workflows -> Workflows -> Editar',
            'Workflows -> Workflows -> Eliminar',
            'Workflows -> Instancias -> Ejecutar',
            'Workflows -> Instancias -> Consultar',
            'Workflows -> Tareas -> Listar',
            'Workflows -> Tareas -> Crear',
            'Workflows -> Tareas -> Editar',
            'Workflows -> Tareas -> Eliminar',
            'Workflows -> Tareas -> Asignar',
            'Workflows -> Archivos -> Subir',
            'Workflows -> Archivos -> Descargar',
            'Workflows -> Archivos -> Eliminar',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();

        $this->command->info('* Iniciando la creación de permisos de Workflows');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos Workflows creados satisfactoriamente');
    }
}
