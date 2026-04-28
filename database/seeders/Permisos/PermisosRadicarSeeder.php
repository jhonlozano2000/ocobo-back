<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermisosRadicarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $Rol1 = 'Administrador';
        $Rol2 = 'Radicador correspondencia recibida';
        $Rol3 = 'Radicador correspondencia enviada';
        $Rol4 = 'Radicador correspondencia interna';
        $Rol5 = 'Radicador PQRSF';

        $this->command->info('*  Iniciando la creación de permisos de Ventanilla Única');

        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Listar'])->syncRoles([$Rol1, $Rol2]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Crear'])->syncRoles([$Rol1, $Rol2]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Editar'])->syncRoles([$Rol1, $Rol2]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Mostrar'])->syncRoles([$Rol1, $Rol2]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Eliminar'])->syncRoles([$Rol1, $Rol2]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Exportar'])->syncRoles([$Rol1, $Rol2]);
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Imprimir Rotulo'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Subir digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Subir adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Eliminar digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Eliminar adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Actualizar asunto'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Atualizar fechas de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Actualizar clasificacion de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Notificar Email'])->syncRoles('Administrador');

        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Listar'])->syncRoles([$Rol1, $Rol3]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Editar'])->syncRoles([$Rol1, $Rol3]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Mostrar'])->syncRoles([$Rol1, $Rol3]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Eliminar'])->syncRoles([$Rol1, $Rol3]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Exportar'])->syncRoles([$Rol1, $Rol3]);
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Imprimir Rotulo'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Subir digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Subir adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Eliminar digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Eliminar adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Actualizar asunto'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Atualizar fechas de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Actualizar clasificacion de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Notificar Email'])->syncRoles('Administrador');

        Permission::create(['name' => 'Radicar -> Cores. Interna -> Listar'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Crear'])->syncRoles([$Rol1]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Editar'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Mostrar'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Eliminar'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Exportar'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Imprimir Rotulo'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Subir digital'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Subir adjuntos'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Eliminar digital'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Eliminar adjuntos'])->syncRoles([$Rol1, $Rol4]);
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Actualizar asunto'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Atualizar fechas de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Actualizar clasificacion de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Notificar Email'])->syncRoles('Administrador');

        Permission::create(['name' => 'Radicar -> PQRSF -> Listar'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Crear'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Editar'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Mostrar'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Eliminar'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Exportar'])->syncRoles([$Rol1, $Rol5]);
        Permission::create(['name' => 'Radicar -> PQRSF -> Imprimir Rotulo'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Subir digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Subir adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Eliminar digital'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Eliminar adjuntos'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Actualizar asunto'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Atualizar fechas de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Actualizar clasificacion de radicados'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Notificar Email'])->syncRoles('Administrador');

        $this->command->info('✅ Permisos Ventanilla Única creados satisfactoriamente');
    }
}
