<?php

namespace Database\Seeders\ControlAcceso;

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

        Role::firstOrCreate(['name' => 'Administrador']);
        Role::firstOrCreate(['name' => 'Radicador correspondencia recibida']);
        Role::firstOrCreate(['name' => 'Radicador correspondencia enviada']);
        Role::firstOrCreate(['name' => 'Radicador correspondencia interna']);
        Role::firstOrCreate(['name' => 'Radicador PQRSF']);
        Role::firstOrCreate(['name' => 'Digitalizador']);
        Role::firstOrCreate(['name' => 'Mi espacion']);

        $this->command->info('✅ Roles creados exitosamente');
    }
}
