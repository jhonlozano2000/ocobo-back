<?php

namespace Tests\Feature\Transversal;

use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MisFirmasTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_own_signatures()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        FirmaEvento::factory()->create([
            'user_id' => $user->id,
            'documentable_id' => 1,
        ]);
        FirmaEvento::factory()->create([
            'user_id' => $otherUser->id,
            'documentable_id' => 999,
        ]);

        $response = $this->actingAs($user)->getJson('/api/transversal/firma-eventos/mis-firmas');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.documentable_id', 1);
    }

    public function test_filters_by_tipo()
    {
        $user = User::factory()->create();

        FirmaEvento::factory()->create([
            'user_id' => $user->id,
            'documentable_type' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
            'documentable_id' => 1,
        ]);
        FirmaEvento::factory()->create([
            'user_id' => $user->id,
            'documentable_type' => 'App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados',
            'documentable_id' => 2,
        ]);

        $response = $this->actingAs($user)->getJson('/api/transversal/firma-eventos/mis-firmas?tipo=reci');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.documentable_type', 'reci');
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/transversal/firma-eventos/mis-firmas');
        $response->assertUnauthorized();
    }

    public function test_returns_paginated_results()
    {
        $user = User::factory()->create();
        FirmaEvento::factory(60)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/transversal/firma-eventos/mis-firmas');

        $response->assertOk();
        $this->assertCount(50, $response->json('data'));
    }

    public function test_per_page_parameter()
    {
        $user = User::factory()->create();
        FirmaEvento::factory(30)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/transversal/firma-eventos/mis-firmas?per_page=10');

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
    }

    public function test_filters_by_date_range()
    {
        $user = User::factory()->create();

        FirmaEvento::factory()->create([
            'user_id' => $user->id,
            'fecha_firma' => now()->subDays(5),
            'documentable_id' => 1,
        ]);
        FirmaEvento::factory()->create([
            'user_id' => $user->id,
            'fecha_firma' => now()->subDays(15),
            'documentable_id' => 2,
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/transversal/firma-eventos/mis-firmas?desde=' . now()->subDays(10)->format('Y-m-d')
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.documentable_id', 1);
    }
}
