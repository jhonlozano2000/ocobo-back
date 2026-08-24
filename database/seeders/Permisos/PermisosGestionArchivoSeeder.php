<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permisos del módulo Gestión de Archivo (M10-M15).
 *
 * Convención: 'Gestion de Archivo -> {Submodulo} -> {Accion}'.
 * Administrador y Jefe de Archivo reciben el set completo.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-24
 */
class PermisosGestionArchivoSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            // Expedientes (M10)
            'Gestion de Archivo -> Expedientes -> Ver',
            'Gestion de Archivo -> Expedientes -> Crear',
            'Gestion de Archivo -> Expedientes -> Editar',
            'Gestion de Archivo -> Expedientes -> Incorporar',
            'Gestion de Archivo -> Expedientes -> Cerrar',

            // Préstamos (M11)
            'Gestion de Archivo -> Prestamos -> Ver',
            'Gestion de Archivo -> Prestamos -> Crear',
            'Gestion de Archivo -> Prestamos -> Devolver',

            // Transferencias y Eliminaciones (M12)
            'Gestion de Archivo -> Transferencias -> Ver',
            'Gestion de Archivo -> Transferencias -> Crear',
            'Gestion de Archivo -> Transferencias -> Aprobar',
            'Gestion de Archivo -> Eliminaciones -> Ver',
            'Gestion de Archivo -> Eliminaciones -> Crear',

            // Reportes (M14)
            'Gestion de Archivo -> Reportes -> Ver',

            // Plantillas de Documentos
            'Gestion de Archivo -> Plantillas -> Ver',
            'Gestion de Archivo -> Plantillas -> Crear',
            'Gestion de Archivo -> Plantillas -> Editar',
            'Gestion de Archivo -> Plantillas -> Eliminar',
            'Gestion de Archivo -> Plantillas -> Descargar',

            // Dashboard (M15)
            'Gestion de Archivo -> Dashboard -> Ver',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();
        $rolJefe = Role::where('name', 'Jefe de Archivo')->first();

        $this->command->info('* Iniciando la creación de permisos de Gestión de Archivo');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);

            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }

            if ($rolJefe) {
                $rolJefe->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos de Gestión de Archivo creados satisfactoriamente');
    }
}
