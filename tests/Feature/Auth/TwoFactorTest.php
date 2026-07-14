<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use PragmaRX\Google2FALaravel\Google2FA;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['estado' => 1]);
        $this->google2fa = app(Google2FA::class);
    }

    public function test_login_returns_token_when_2fa_enabled()
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => ['requires_2fa' => true],
            ]);
        $this->assertArrayHasKey('two_factor_token', $response['data']);
    }

    public function test_setup_returns_qr_and_secret()
    {
        $response = $this->actingAs($this->user)->getJson('/api/2fa/setup');

        $response->assertStatus(200);
        $this->assertArrayHasKey('qr_svg', $response['data']);
        $this->assertNotEmpty($response['data']['qr_svg']);
        $this->assertNotNull($this->user->fresh()->two_factor_secret);
    }

    public function test_confirm_activates_2fa_with_valid_code()
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update(['two_factor_secret' => $secret]);

        $code = $this->google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($this->user)->postJson('/api/2fa/confirm', [
            'code' => $code,
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($this->user->fresh()->two_factor_confirmed_at);
        $this->assertNotNull($this->user->fresh()->two_factor_recovery_codes);
    }

    public function test_disable_requires_password()
    {
        $this->user->update([
            'two_factor_secret' => $this->google2fa->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode([Hash::make('code1')]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/2fa/disable', [
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $this->assertNull($this->user->fresh()->two_factor_confirmed_at);
    }

    public function test_verify_completes_login_with_valid_totp()
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        // Login step 1: get token
        $loginResponse = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);
        $token = $loginResponse['data']['two_factor_token'];

        // Login step 2: verify with TOTP
        $code = $this->google2fa->getCurrentOtp($secret);
        $verifyResponse = $this->postJson('/api/2fa/verify', [
            'two_factor_token' => $token,
            'code' => $code,
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => ['user' => ['id' => $this->user->id]],
            ]);
    }
}
