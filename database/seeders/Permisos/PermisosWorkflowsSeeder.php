<?php

namespace Database\Seeders\Permisos;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosWorkflowsSeeder extends Seeder
{
    public const ROL_DISENADOR = 'Diseñador de flujos';

    public const ROL_EJECUTOR = 'Ejecutor de flujos';

    /**
     * M19-11: el ejecutor trabaja sus tareas y las instancias, pero no diseña flujos
     * ni asigna o elimina tareas. Editar una tarea además exige ser propietario o responsable.
     */
    public const PERMISOS_EJECUTOR = [
        'Workflows -> Workflows -> Listar',
        'Workflows -> Workflows -> Mostrar',
        'Workflows -> Instancias -> Ejecutar',
        'Workflows -> Instancias -> Consultar',
        'Workflows -> Tareas -> Listar',
        'Workflows -> Tareas -> Mostrar',
        'Workflows -> Tareas -> Crear',
        'Workflows -> Tareas -> Editar',
        'Workflows -> Archivos -> Ver',
        'Workflows -> Archivos -> Subir',
        'Workflows -> Archivos -> Descargar',
    ];

    public function run(): void
    {
        $permisos = [
            'Workflows -> Workflows -> Listar',
            'Workflows -> Workflows -> Crear',
            'Workflows -> Workflows -> Mostrar',
            'Workflows -> Workflows -> Editar',
            'Workflows -> Workflows -> Eliminar',
            'Workflows -> Instancias -> Ejecutar',
            'Workflows -> Instancias -> Consultar',
            'Workflows -> Tareas -> Listar',
            'Workflows -> Tareas -> Mostrar',
            'Workflows -> Tareas -> Crear',
            'Workflows -> Tareas -> Editar',
            'Workflows -> Tareas -> Eliminar',
            'Workflows -> Tareas -> Asignar',
            'Workflows -> Archivos -> Ver',
            'Workflows -> Archivos -> Subir',
            'Workflows -> Archivos -> Descargar',
            'Workflows -> Archivos -> Eliminar',
        ];

        $rolAdmin = Role::where('name', 'Administrador')->first();

        $this->command->info('* Iniciando la creación de permisos de Workflows');

        foreach ($permisos as $permiso) {
            $permission = Permission::firstOrCreate(['name' => $permiso]);
            if ($rolAdmin) {
                $rolAdmin->givePermissionTo($permission);
            }
        }

        // M19-11: perfiles operativos del módulo (idempotente; no quita permisos agregados a mano)
        Role::firstOrCreate(['name' => self::ROL_DISENADOR, 'guard_name' => 'web'])->givePermissionTo($permisos);
        Role::firstOrCreate(['name' => self::ROL_EJECUTOR, 'guard_name' => 'web'])->givePermissionTo(self::PERMISOS_EJECUTOR);

        $this->command->info('✅ Permisos Workflows creados satisfactoriamente');
    }
}
