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
        $Rol2 = Role::create(['name' => 'Radicador correspondencia recibida']);
        $Rol3 = Role::create(['name' => 'Radicador correspondencia enviada']);
        $Rol4 = Role::create(['name' => 'Radicador correspondencia interna']);
        $Rol5 = Role::create(['name' => 'Radicador PQRSF']);
        $Rol6 = Role::create(['name' => 'Digitalizador']);
        $Rol7 = Role::create(['name' => 'Mi espacion']);


        /**
         * Control de acceso
         */
        Permission::create(['name' => 'Control de acceso - Usuarios -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Usuarios -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Usuarios -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Usuarios -> Mostrar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Usuarios -> Eliminar'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'Control de acceso - Roles -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Roles -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Roles -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Roles -> Mostrar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Control de acceso - Roles -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Configuración
         */
        Permission::create(['name' => 'Config - División política -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - División política -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - División política -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - División política -> Mostrar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - División política -> Eliminar'])->syncRoles([$Rol1]);

        /** Esto es para gestionar las plantilla de la comunicacion oficiales - Por ahora no se va a implementar, esto lo vamos
         * a dejar de ultimo con el mudo de edicion de documento de forma colaborativa y en caliente
         **/
        Permission::create(['name' => 'Config - Gestión de plantillas -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Gestión de plantillas -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Gestión de plantillas -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Gestión de plantillas -> Mostrar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Gestión de plantillas -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Configuración de servidor de almacenamiento
         */
        Permission::create(['name' => 'Config - Servidor de almacenamiento -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Servidor de almacenamiento -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Servidor de almacenamiento -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Servidor de almacenamiento -> Mostrar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Servidor de almacenamiento -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Configuración de listas
         */
        Permission::create(['name' => 'Config - Listas -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Listas -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Listas -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Listas -> Mostrar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Listas -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Configuración de otras configuraciones
         */
        Permission::create(['name' => 'Config - Otras configuraciones -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Otras configuraciones -> Editar'])->syncRoles([$Rol1]);

        /**
         * Configuración de sedes
         */
        Permission::create(['name' => 'Config - Sedes -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Sedes -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Sedes -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Sedes -> Mostrar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Sedes -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Configuracion ventannillas
         */
        Permission::create(['name' => 'Config - Ventanillas -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Ventanillas -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Ventanillas -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Ventanillas -> Mostrar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Config - Ventanillas -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Radicación
         */
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Listar'])->syncRoles([$Rol1, $Rol2]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Crear'])->syncRoles([$Rol1, $Rol2]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Editar'])->syncRoles([$Rol1, $Rol2]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Mostrar'])->syncRoles([$Rol1, $Rol2]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Eliminar'])->syncRoles([$Rol1, $Rol2]);

        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Listar'])->syncRoles([$Rol1, $Rol3]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Editar'])->syncRoles([$Rol1, $Rol3]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Mostrar'])->syncRoles([$Rol1, $Rol3]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Eliminar'])->syncRoles([$Rol1, $Rol3]);

        Permission::create(['name' => 'Radicar -> Cores. Interna -> Listar'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Editar'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Mostrar'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Eliminar'])->syncRoles([$Rol1, $Rol4]);

        Permission::create(['name' => 'Radicar -> PQRSF -> Listar'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Crear'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Editar'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Mostrar'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Eliminar'])->syncRoles([$Rol1, $Rol5]);

        /**
         * Calidad
         */
        Permission::create(['name' => 'Calidad - Organigrama -> Listar'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Calidad - Organigrama -> Crear'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Calidad - Organigrama -> Editar'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Calidad - Organigrama -> Mostrar'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Calidad - Organigrama -> Eliminar'])->syncRoles([$Rol1, $Rol5]);

        Permission::create(['name' => 'Calidad -> Crear procesos'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Calidad -> Crear Macro Procesos'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Calidad -> Crear Procedimientos'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Calidad -> Crear Tipos de Archivos'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Calidad -> Cargar Archivos'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Calidad -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Clasificacion documental
         */
        Permission::create(['name' => 'TRD -> Importar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TRD -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TRD -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TRD -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TRD -> Mostrar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TRD -> Eliminar'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'TVD -> Importar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TVD -> Listar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TVD -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TVD -> Editar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TVD -> Mostrar'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'TVD -> Eliminar'])->syncRoles([$Rol1]);

        /**
         * Gestion
         */
        Permission::create(['name' => 'Gestión -> Terceros'])->syncRoles([$Rol1]);

        /**
         * Digitalizaxion
         */
        Permission::create(['name' => 'Digitalización -> Listar'])->syncRoles([$Rol1, $Rol6]);
        Permission::create(['name' => 'Digitalización -> Crear'])->syncRoles([$Rol1, $Rol6]);
        Permission::create(['name' => 'Digitalización -> Editar'])->syncRoles([$Rol1, $Rol6]);
        Permission::create(['name' => 'Digitalización -> Mostrar'])->syncRoles([$Rol1, $Rol6]);
        Permission::create(['name' => 'Digitalización -> Eliminar'])->syncRoles([$Rol1, $Rol6]);

        /**
         * Digitalizaxion
         */
        Permission::create(['name' => 'Mi Espacio -> Comunicaciones'])->syncRoles([$Rol1, $Rol7]);
        Permission::create(['name' => 'Mi Espacio -> Mi Disco'])->syncRoles([$Rol1, $Rol7]);
        Permission::create(['name' => 'Mi Espacio -> Archivos digitalizados'])->syncRoles([$Rol1, $Rol7]);

        /**
         * Reportes
         */
        Permission::create(['name' => 'Reporte - Ventanilla -> Imprimir planillas correspondencia recibida'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Reporte - Ventanilla -> Imprimir planillas correspondencia enviada'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Reporte - Ventanilla -> Imprimir planillas correspondencia interna'])->syncRoles([$Rol1]);

        Permission::create(['name' => 'Reporte -> Indicadores'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Reporte -> Detallados'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Reporte -> Estadisticos'])->syncRoles([$Rol1]);

        /**
         * Otros permisos
         */
        Permission::create(['name' => 'Jefe de Archivo'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Puede Crear Expediente'])->syncRoles([$Rol1]);
    }
}
