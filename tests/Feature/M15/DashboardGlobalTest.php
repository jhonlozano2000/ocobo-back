<?php

namespace Tests\Feature\M15;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardGlobalTest extends TestCase
{

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/dashboard/global');
        $response->assertUnauthorized();
    }

    public function test_returns_aggregated_structure()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard/global');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'radicados_recibidos',
                'radicados_enviados',
                'pqrs',
                'archivo' => ['expedientes', 'prestamos', 'transferencias'],
                'usuarios',
                'firmas' => ['hoy', 'semana', 'mes'],
                'vencimientos_proximos',
            ],
        ]);
    }

    public function test_firmas_counts_are_integers()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard/global');

        $response->assertOk();
        $firmas = $response->json('data.firmas');
        $this->assertIsInt($firmas['hoy']);
        $this->assertIsInt($firmas['semana']);
        $this->assertIsInt($firmas['mes']);
    }

    public function test_vencimientos_proximos_is_array()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard/global');

        $response->assertOk();
        $this->assertIsArray($response->json('data.vencimientos_proximos'));
    }
}
