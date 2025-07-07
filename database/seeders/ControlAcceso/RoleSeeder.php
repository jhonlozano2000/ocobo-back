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
        Permission::create(['name' => 'Config - División política -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - División política -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - División política -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - División política -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - División política -> Eliminar'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'Config - Gestión de plantillas -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Gestión de plantillas -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Gestión de plantillas -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Gestión de plantillas -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Gestión de plantillas -> Eliminar'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'Config - Medios de recepción -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Medios de recepción -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Medios de recepción -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Medios de recepción -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Medios de recepción -> Eliminar'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'Config - Plantilla de documentos -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Plantilla de documentos -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Plantilla de documentos -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Plantilla de documentos -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Plantilla de documentos -> Eliminar'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'Config - Tipos de radicados -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Tipos de radicados -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Tipos de radicados -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Tipos de radicados -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Tipos de radicados -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Radicación
         */
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Eliminar'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Eliminar'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'Radicar -> Cores. Interna -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Eliminar'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'Radicar -> PQRSF -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Calidad
         */
        Permission::create(['name' => 'Calidad -> Crear procesos'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Calidad -> Crear Macro Procesos'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Calidad -> Crear Procedimientos'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Calidad -> Crear Tipos de Archivos'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Calidad -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Clasificacion documental
         */
        Permission::create(['name' => 'TRD -> Importar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TRD -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TRD -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TRD -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TRD -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TRD -> Eliminar'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'TVD -> Importar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TVD -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TVD -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TVD -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TVD -> Mostar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TVD -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Reportes
         */
        Permission::create(['name' => 'Reporte - Ventanilla -> Imprimir planillas correspondencia recibida'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Reporte - Ventanilla -> Imprimir planillas correspondencia enviada'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Reporte - Ventanilla -> Imprimir planillas correspondencia interna'])->syncRoles([$Rol1]);

        /**
         * Otros permisos
         */
        Permission::create(['name' => 'Jefe de Archivo'])->syncRoles([$Rol1]);
    }
}
