<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosPlantillasDocumentosSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'Gestión Archivo - Plantillas Documentos -> Listar',
            'Gestión Archivo - Plantillas Documentos -> Crear',
            'Gestión Archivo - Plantillas Documentos -> Editar',
            'Gestión Archivo - Plantillas Documentos -> Eliminar',
            'Gestión Archivo - Plantillas Documentos -> Descargar',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();

        $this->command->info('*  Iniciando la creación de permisos de Plantillas de Documentos');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
        }

        $this->command->info('✅ Permisos Plantillas de Documentos creados satisfactoriamente');
    }
}
