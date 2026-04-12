<?php

namespace Database\Factories\Configuracion;

use App\Models\Configuracion\ConfigDiviPoli;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConfigDiviPoliFactory extends Factory
{
    protected $model = ConfigDiviPoli::class;

    public function definition(): array
    {
        return [
            'divi_poli_id' => null,
            'nombre' => $this->faker->unique()->word(),
            'tipo' => $this->faker->randomElement(['Pais', 'Departamento', 'Municipio']),
            'codigo' => strtoupper($this->faker->unique()->bothify('??###')),
            'estado' => 1,
        ];
    }

    public function pais(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'Pais',
            'codigo' => strtoupper($this->faker->unique()->countryCode()),
        ]);
    }

    public function departamento(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'Departamento',
            'codigo' => strtoupper($this->faker->unique()->bothify('##')),
        ]);
    }

    public function municipio(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'Municipio',
            'codigo' => strtoupper($this->faker->unique()->bothify('#####')),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 0,
        ]);
    }
}