<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'Config - División política -> Listar',
            'Config - División política -> Crear',
            'Config - División política -> Editar',
            'Config - División política -> Mostrar',
            'Config - División política -> Eliminar',
            'Config - División política -> Exportar',
            'Config - Gestión de plantillas -> Listar',
            'Config - Gestión de plantillas -> Crear',
            'Config - Gestión de plantillas -> Editar',
            'Config - Gestión de plantillas -> Mostrar',
            'Config - Gestión de plantillas -> Eliminar',
            'Config - Gestión de plantillas -> Exportar',
            'Config - Servidor de almacenamiento -> Listar',
            'Config - Servidor de almacenamiento -> Crear',
            'Config - Servidor de almacenamiento -> Editar',
            'Config - Servidor de almacenamiento -> Mostrar',
            'Config - Servidor de almacenamiento -> Eliminar',
            'Config - Servidor de almacenamiento -> Exportar',
            'Config - Listas -> Listar',
            'Config - Listas -> Crear',
            'Config - Listas -> Editar',
            'Config - Listas -> Mostrar',
            'Config - Listas -> Eliminar',
            'Config - Listas -> Exportar',
            'Config - Otras configuraciones -> Listar',
            'Config - Otras configuraciones -> Editar',
            'Config - Calendario -> Listar',
            'Config - Calendario -> Crear',
            'Config - Calendario -> Editar',
            'Config - Calendario -> Eliminar',
            'Config - Sedes -> Listar',
            'Config - Sedes -> Crear',
            'Config - Sedes -> Editar',
            'Config - Sedes -> Mostrar',
            'Config - Sedes -> Eliminar',
            'Config - Sedes -> Exportar',
            'Config - Ventanillas -> Listar',
            'Config - Ventanillas -> Crear',
            'Config - Ventanillas -> Editar',
            'Config - Ventanillas -> Mostrar',
            'Config - Ventanillas -> Eliminar',
            'Config - Ventanillas -> Exportar',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();

        $this->command->info('*  Iniciando la creación de permisos de Configuración');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos Configuración creados satisfactoriamente');
    }
}
