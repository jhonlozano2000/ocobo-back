<?php

namespace Database\Factories\Transversal;

use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FirmaEventoFactory extends Factory
{
    protected $model = FirmaEvento::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'documentable_type' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
            'documentable_id' => 1,
            'hash_original' => $this->faker->sha256,
            'hash_firmado' => $this->faker->sha256,
            'otp_utilizado' => $this->faker->numerify('######'),
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'fecha_firma' => now(),
        ];
    }
}
