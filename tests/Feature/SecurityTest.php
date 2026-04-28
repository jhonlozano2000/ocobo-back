<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SecurityTest extends TestCase
{
    /** @test */
    public function rate_limiting_login(): void
    {
        // Intentar más de 5 logins en un minuto
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword'
            ]);
        }

        // El 6to debe ser 429
        $response->assertStatus(429);
    }

    /** @test */
    public function unauthenticated_cannot_access_api(): void
    {
        $response = $this->getJson('/api/ventanilla/recibidos');
        $response->assertStatus(401);
    }

    /** @test */
    public function authenticated_can_access_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/ventanilla/recibidos');

        $response->assertStatus(200);
    }

    /** @test */
    public function cors_headers_present(): void
    {
        $response = $this->getJson('/api/ventanilla/recibidos', [
            'Origin' => 'http://localhost:3000'
        ]);

        $response->assertHeader('Access-Control-Allow-Origin');
    }

    /** @test */
    public function security_headers_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-XSS-Protection');
        $response->assertHeader('X-Content-Type-Options');
        $response->assertHeader('X-Frame-Options');
    }

    /** @test */
    public function no_sensitive_data_in_response(): void
    {
        $user = User::factory()->create([
            'password' => 'secretpassword'
        ]);

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertDontSee('secretpassword');
        $response->assertDontSee('password');
    }
}