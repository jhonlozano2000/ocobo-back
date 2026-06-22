<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosPqrsSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'Radicar -> PQRSF -> Listar',
            'Radicar -> PQRSF -> Crear',
            'Radicar -> PQRSF -> Editar',
            'Radicar -> PQRSF -> Mostrar',
            'Radicar -> PQRSF -> Eliminar',
            'Radicar -> PQRSF -> Exportar',
            'Radicar -> PQRSF -> Cambiar Estado',
            'Radicar -> PQRSF -> Aplicar Prorroga',
            'Radicar -> PQRSF -> Actualizar asunto',
            'Radicar -> PQRSF -> Atualizar fechas de radicados',
            'Radicar -> PQRSF -> Actualizar clasificacion de radicados',
            'Radicar -> PQRSF -> Notificar Email',
            'Radicar -> PQRSF -> Imprimir Rotulo',
            'Radicar -> PQRSF -> Subir digital',
            'Radicar -> PQRSF -> Subir adjuntos',
            'Radicar -> PQRSF -> Eliminar digital',
            'Radicar -> PQRSF -> Eliminar adjuntos',
            'Radicar -> PQRSF -> Firmar peticionario',
            'Radicar -> PQRSF -> Anular',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();
        $rolRadicadorPQRSF = Role::where('name', 'Radicador PQRSF')->first();

        $this->command->info('*  Iniciando la creación de permisos de PQRS');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
            if ($rolRadicadorPQRSF) {
                $rolRadicadorPQRSF->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos PQRS creados satisfactoriamente');
    }
}
