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
 *
 * @date 2026-08-24
 */
class PermisosGestionArchivoSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            // Expedientes (M10)
            'Gestion de Archivo -> Expedientes -> Ver',
            // Archivo central: expedientes de todas las dependencias (M08-03)
            'Gestion de Archivo -> Expedientes -> Ver Todos',
            'Gestion de Archivo -> Expedientes -> Crear',
            'Gestion de Archivo -> Expedientes -> Editar',
            'Gestion de Archivo -> Expedientes -> Incorporar',
            'Gestion de Archivo -> Expedientes -> Cerrar',
            // B13: reabrir es un permiso distinto de cerrar (diseño de expedientes §3.5)
            'Gestion de Archivo -> Expedientes -> Reabrir',
            // B20: catálogo topográfico (depósitos, estantes, entrepaños y cajas)
            'Gestion de Archivo -> Ubicaciones -> Administrar',

            // Préstamos (M11)
            'Gestion de Archivo -> Prestamos -> Ver',
            'Gestion de Archivo -> Prestamos -> Crear',
            'Gestion de Archivo -> Prestamos -> Devolver',
            // §3.10: cualquier usuario solicita; aprueba el responsable del archivo de la fase
            'Gestion de Archivo -> Prestamos -> Solicitar',
            'Gestion de Archivo -> Prestamos -> Aprobar',
            // §7: prestar clasificados y reservados exige poder verlos
            'Gestion de Archivo -> Expedientes -> Ver reservados',

            // Transferencias y Eliminaciones (M12)
            'Gestion de Archivo -> Transferencias -> Ver',
            'Gestion de Archivo -> Transferencias -> Crear',
            'Gestion de Archivo -> Transferencias -> Aprobar',
            // B17: el archivo de destino recibe el lote; quien lo envía no puede recibirlo
            'Gestion de Archivo -> Transferencias -> Recibir',
            'Gestion de Archivo -> Eliminaciones -> Ver',
            'Gestion de Archivo -> Eliminaciones -> Crear',
            // Quien puede figurar como aprobador de una eliminación (M08-10)
            'Gestion de Archivo -> Eliminaciones -> Aprobar',

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

        // §7: solicitar un préstamo es para cualquier usuario
        Role::all()->each(fn (Role $rol) => $rol->givePermissionTo('Gestion de Archivo -> Prestamos -> Solicitar'));

        $this->command->info('✅ Permisos de Gestión de Archivo creados satisfactoriamente');
    }
}
