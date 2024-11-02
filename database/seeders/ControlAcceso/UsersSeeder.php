<?php

namespace Database\Seeders\ControlAcceso;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create(['num_docu' => '123456', 'nombres' => 'administrador', 'apellidos' => 'administrador', 'email' => 'admin@admin.com', 'password' => bcrypt('123456')])->assignRole('Administrador');
    }
}
