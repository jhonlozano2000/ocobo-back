<?php

namespace Database\Factories;

use App\Models\ReporteProgramado;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReporteProgramadoFactory extends Factory
{
    protected $model = ReporteProgramado::class;

    public function definition(): array
    {
        return [
            'modulo' => $this->faker->randomElement([
                'recibidas', 'enviadas', 'internas', 'pqrs',
                'expedientes', 'prestamos', 'transferencias',
            ]),
            'filtros' => [],
            'formato' => $this->faker->randomElement(['pdf', 'excel']),
            'periodicidad' => $this->faker->randomElement(['daily', 'weekly', 'monthly']),
            'asunto' => $this->faker->sentence(3),
            'destinatarios' => [$this->faker->email()],
            'ultima_ejecucion' => null,
            'proxima_ejecucion' => now()->addDay(),
            'activo' => true,
        ];
    }
}
