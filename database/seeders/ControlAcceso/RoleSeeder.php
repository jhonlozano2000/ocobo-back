<?php

namespace Database\Seeders\ControlAcceso;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('* Creando roles');

        Role::create(['name' => 'Administrador']);
        Role::create(['name' => 'Radicador correspondencia recibida']);
        Role::create(['name' => 'Radicador correspondencia enviada']);
        Role::create(['name' => 'Radicador correspondencia interna']);
        Role::create(['name' => 'Radicador PQRSF']);
        Role::create(['name' => 'Digitalizador']);
        Role::create(['name' => 'Mi espacion']);

        $this->command->info('✅ Roles creados exitosamente');
    }
}