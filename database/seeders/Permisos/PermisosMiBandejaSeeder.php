<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosMiBandejaSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            // Mi Correspondencia
            'Mi Bandeja - Mi Correspondencia -> Ver',
            'Mi Bandeja - Mi Correspondencia -> Crear',
            'Mi Bandeja - Mi Correspondencia -> Editar',
            'Mi Bandeja - Mi Correspondencia -> Eliminar',

            // Recibidos
            'Mi Bandeja - Recibidos -> Ver',
            'Mi Bandeja - Recibidos -> Editar Estado',
            'Mi Bandeja - Recibidos -> Editor Colaborativo',
            'Mi Bandeja - Recibidos -> Agregar Comentarios',

            // Enviados
            'Mi Bandeja - Enviados -> Ver',
            'Mi Bandeja - Enviados -> Editar',
            'Mi Bandeja - Enviados -> Agregar Comentarios',

            // Internos
            'Mi Bandeja - Internos -> Ver',
            'Mi Bandeja - Internos -> Editar',
            'Mi Bandeja - Internos -> Agregar Comentarios',

            // Mi Disco
            'Mi Bandeja - Mi Disco -> Ver',
            'Mi Bandeja - Mi Disco -> Subir Archivos',
            'Mi Bandeja - Mi Disco -> Descargar',
            'Mi Bandeja - Mi Disco -> Eliminar',
            'Mi Bandeja - Mi Disco -> Crear Carpetas',
            'Mi Bandeja - Mi Disco -> Compartir',

            // Expedientes Digitales
            'Mi Bandeja - Expedientes -> Ver',
            'Mi Bandeja - Expedientes -> Crear',
            'Mi Bandeja - Expedientes -> Editar',
            'Mi Bandeja - Expedientes -> Eliminar',
            'Mi Bandeja - Expedientes -> Agregar Documentos',
            'Mi Bandeja - Expedientes -> Cerrar Expediente',
            'Mi Bandeja - Expedientes -> ConsultarHistoria',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();
        $rolRadicador = Role::where('name', 'Radicador correspondencia recibida')->first();
        $rolDigitalizador = Role::where('name', 'Digitalizador')->first();

        $this->command->info('*   Iniciando la creación de permisos de Mi Bandeja');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);

            // Administrador tiene todos los permisos
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }

            // Radicador recibe tiene permisos de Recibidos
            if ($rolRadicador && str_contains($permiso, 'Recibidos')) {
                $rolRadicador->givePermissionTo($permission);
            }

            // Digitalizador tiene permisos de Mi Disco
            if ($rolDigitalizador && str_contains($permiso, 'Mi Disco')) {
                $rolDigitalizador->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos de Mi Bandeja creados satisfactoriamente');
    }
}
