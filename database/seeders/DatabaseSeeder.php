<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Database\Seeders\Calidad\OrganigramaSeed;
use Database\Seeders\Configuracion\DiviPoliSeed;
use Database\Seeders\Configuracion\ListaSeed;
use Database\Seeders\ControlAcceso\RoleSeeder;
use Database\Seeders\ControlAcceso\UsersSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call(RoleSeeder::class);
        $this->call(UsersSeeder::class);
        $this->call(OrganigramaSeed::class);
        $this->call(ListaSeed::class);
        $this->call(DiviPoliSeed::class);
    }
}
