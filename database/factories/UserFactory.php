<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'divi_poli_id' => null,
            'num_docu' => $this->faker->unique()->numerify('###########'),
            'nombres' => $this->faker->firstName(),
            'apellidos' => $this->faker->lastName(),
            'tel' => $this->faker->optional()->numerify('##########'), // Max 10 dígitos
            'movil' => $this->faker->optional()->numerify('##########'), // Max 10 dígitos
            'dir' => $this->faker->optional()->randomElement(['Calle 123', 'Carrera 45', 'Avenida 67']),
            'email' => $this->faker->unique()->safeEmail(),
            'firma' => null,
            'avatar' => null,
            'password' => static::$password ??= Hash::make('password'),
            'estado' => 1,
        ];
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 0,
        ]);
    }
}