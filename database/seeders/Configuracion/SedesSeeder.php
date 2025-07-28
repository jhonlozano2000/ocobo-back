<?php

namespace Database\Seeders\Configuracion;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class SedesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $faker = Faker::create();
        for ($i = 0; $i < 1; $i++) {
            DB::table('config_sedes')->insert([
                'nombre' =>  $faker->name,
                'codigo' =>  $faker->unique()->word,
                'direccion' => $faker->address,
                'telefono' => $faker->phoneNumber,
                'email' =>  $faker->email,
                'ubicacion' => $faker->address,
            ]);
        }
    }
}
