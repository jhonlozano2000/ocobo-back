<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosMiBandejaTempSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            // Grupos Colaborativos
            'Mi Bandeja - Grupos Colaborativos -> Ver',
            'Mi Bandeja - Grupos Colaborativos -> Crear',
            'Mi Bandeja - Grupos Colaborativos -> Editar',
            'Mi Bandeja - Grupos Colaborativos -> Eliminar',
            'Mi Bandeja - Grupos Colaborativos -> Gestionar Miembros',
            'Mi Bandeja - Grupos Colaborativos -> Subir Adjuntos',
            'Mi Bandeja - Grupos Colaborativos -> Enviar Tramite',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();
        $rolRadicador = Role::where('name', 'Radicador correspondencia recibida')->first();
        $rolDigitalizador = Role::where('name', 'Digitalizador')->first();
        $rolJefeArchivo = Role::where('name', 'Jefe de Archivo')->first();

        $this->command->info('*   Iniciando la creación de permisos de Mi Bandeja Grupos Colaborativos');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);

            // Administrador tiene todos los permisos
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }

            // Radicador tiene permisos de ver y crear
            if ($rolRadicador && in_array($permiso, ['Ver', 'Crear', 'Editar', 'Gestionar Miembros', 'Subir Adjuntos'])) {
                $rolRadicador->givePermissionTo($permission);
            }

            // Digitalizador tiene permisos de adjuntos
            if ($rolDigitalizador && $permiso === 'Mi Bandeja - Grupos Colaborativos -> Subir Adjuntos') {
                $rolDigitalizador->givePermissionTo($permission);
            }

            // Jefe de Archivo tiene permisos de enviar tramite
            if ($rolJefeArchivo && $permiso === 'Mi Bandeja - Grupos Colaborativos -> Enviar Tramite') {
                $rolJefeArchivo->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos de Mi Bandeja Grupos Colaborativos creados satisfactoriamente');
    }
}
