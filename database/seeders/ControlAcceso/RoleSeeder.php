<?php

namespace Database\Seeders\ControlAcceso;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $Rol1 = Role::create(['name' => 'Administrador']);
        $Rol2 = Role::create(['name' => 'Radicador']);

        /**
         * Control de acceso
         */
        Permission::create(['name' => 'Control de acceso - Usuarios -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Usuarios -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Usuarios -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Usuarios -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Usuarios -> Eliminar'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'Control de acceso - Roles -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Roles -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Roles -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Roles -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Roles -> Eliminar'])->syncRoles([$Rol1]);


        /**
         * Configuración
         */
        Permission::create(['name' => 'Configuración - Plantilla de documentos -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Configuración - Plantilla de documentos -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Configuración - Plantilla de documentos -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Configuración - Plantilla de documentos -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Configuración - Plantilla de documentos -> Eliminar'])->syncRoles([$Rol1]);
    }
}
