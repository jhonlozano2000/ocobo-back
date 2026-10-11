<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosClasificacionDocumentalSeeder extends Seeder
{
    public function run(): void
    {
        $permisosTRD = [
            'TRD -> Listar',
            'TRD -> Mostrar',
            'TRD -> Crear',
            'TRD -> Editar',
            'TRD -> Eliminar',
            'TRD -> Exportar',
            'TRD -> Importar',
            'TRD -> Versiones',
        ];

        $permisosTVD = [
            'TVD -> Listar',
            'TVD -> Mostrar',
            'TVD -> Crear',
            'TVD -> Editar',
            'TVD -> Eliminar',
            'TVD -> Exportar',
            'TVD -> Importar',
            'TVD -> Versiones',
        ];

        $permisos = array_merge($permisosTRD, $permisosTVD);

        $rolAdmin = Role::where('name', 'Administrador')->first();
        // El Jefe de Archivo revisa y aprueba versiones TRD (M10-02) y la TVD (M11-03)
        $rolJefe = Role::where('name', 'Jefe de Archivo')->first();
        $permisosJefe = ['TRD -> Listar', 'TRD -> Mostrar', 'TRD -> Versiones', 'TVD -> Listar', 'TVD -> Mostrar', 'TVD -> Versiones'];

        $this->command->info('*  Iniciando la creación de permisos de Clasificación Documental');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
            if ($rolJefe && in_array($permiso, $permisosJefe, true)) {
                $rolJefe->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos Clasificación Documental creados satisfactoriamente');
    }
}
