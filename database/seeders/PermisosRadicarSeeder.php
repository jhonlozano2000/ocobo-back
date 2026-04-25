<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosRadicarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Subir digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Subir adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Eliminar digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Eliminar adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Actualizar asunto'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Atualizar fechas de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Actualizar clasificacion de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Notificar Email'])->syncRoles('Administrador');

        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Subir digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Subir adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Eliminar digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Eliminar adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Actualizar asunto'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Atualizar fechas de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Actualizar clasificacion de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Notificar Email'])->syncRoles('Administrador');

        Permission::create(['name' => 'Radicar -> Cores. Interna -> Subir digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Subir adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Eliminar digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Eliminar adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Actualizar asunto'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Atualizar fechas de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Actualizar clasificacion de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Notificar Email'])->syncRoles('Administrador');

        Permission::create(['name' => 'Radicar -> PQRSF -> Subir digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Subir adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Eliminar digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Eliminar adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Actualizar asunto'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Atualizar fechas de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Actualizar clasificacion de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Notificar Email'])->syncRoles('Administrador');
    }
}
