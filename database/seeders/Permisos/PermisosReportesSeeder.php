<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosReportesSeeder extends Seeder
{
    public function run(): void
    {


        $permisos = [
            'Reporte - Ventanilla -> Imprimir planillas correspondencia recibida',
            'Reporte - Ventanilla -> Imprimir planillas correspondencia enviada',
            'Reporte - Ventanilla -> Imprimir planillas correspondencia interna',
            'Reporte -> Indicadores',
            'Reporte -> Detallados',
            'Reporte -> Estadisticos',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();

        $this->command->info('*  Iniciando la creación de permisos de Reportes');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos Reportes creados satisfactoriamente');
    }
}
