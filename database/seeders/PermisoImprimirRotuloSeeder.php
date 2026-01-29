<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermisoImprimirRotuloSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::create(['name' => 'Radicar -> Cores. Recibida -> Imprimir Rotulo'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Enviada -> Imprimir Rotulo'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> Cores. Interna -> Imprimir Rotulo'])->syncRoles('Administrador');
        Permission::create(['name' => 'Radicar -> PQRSF -> Imprimir Rotulo'])->syncRoles('Administrador');
    }
}
