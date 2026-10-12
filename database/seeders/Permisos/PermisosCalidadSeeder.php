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
        ];

        // M17-04: eliminar permisos sin funcionalidad ni controlador implementado (ISO 9001 no implementado)
        $permisosObsoletos = [
            'Calidad -> Crear procesos',
            'Calidad -> Crear Macro Procesos',
            'Calidad -> Crear Procedimientos',
            'Calidad -> Crear Tipos de Archivos',
            'Calidad -> Cargar Archivos',
            'Calidad -> Exportar',
        ];

        foreach ($permisosObsoletos as $obsoleto) {
            Permission::where('name', $obsoleto)->delete();
        }

        // M17-03: el radicador de PQRSF solo consulta el organigrama (selección de dependencia/oficina).
        // Crear, editar o eliminar nodos cambia la jerarquía ABAC de todos los usuarios.
        $permisosRadicadorPQRSF = [
            'Calidad - Organigrama -> Listar',
            'Calidad - Organigrama -> Mostrar',
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
                // Revoca lo que versiones anteriores de este seeder le asignaron de más
                in_array($permiso, $permisosRadicadorPQRSF, true)
                    ? $rolRadicadorPQRSF->givePermissionTo($permission)
                    : $rolRadicadorPQRSF->revokePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos Calidad creados satisfactoriamente');
    }
}
