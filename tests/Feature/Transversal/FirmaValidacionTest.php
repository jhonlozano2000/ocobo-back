<?php

namespace Tests\Feature\Transversal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FirmaValidacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function insertRadicadoRecibido(array $data): int
    {
        return DB::table('ventanilla_radica_reci')->insertGetId(array_merge([
            'num_radicado' => 'VAL-' . uniqid(),
            'clasifica_documen_id' => 1,
            'tercero_id' => 1,
            'medio_recep_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $data));
    }

    public function test_validar_returns_integrity_true_when_hash_matches()
    {
        Storage::fake('radicados_recibidos');

        $user = User::factory()->create(['estado' => 1]);
        $file = UploadedFile::fake()->create('documento.pdf', 100);
        $path = $file->store('', 'radicados_recibidos');

        $hashOriginal = hash_file('sha256', Storage::disk('radicados_recibidos')->path($path));

        $documentoId = $this->insertRadicadoRecibido([
            'num_radicado' => 'VAL-INTEGRIDAD-001',
            'archivo_digital' => $path,
            'hash_sha256' => $hashOriginal,
            'estado_firma' => 'firmado',
        ]);

        DB::table('firmas_eventos')->insert([
            'documentable_id' => $documentoId,
            'documentable_type' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
            'user_id' => $user->id,
            'hash_original' => $hashOriginal,
            'hash_firmado' => $hashOriginal,
            'otp_utilizado' => '***123',
            'user_agent' => 'test',
            'ip_address' => '127.0.0.1',
            'fecha_firma' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/transversal/firma-validar', [
            'documentable_type' => 'radicado_recibido',
            'documentable_id' => $documentoId,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valido', true)
            ->assertJsonPath('data.hash_actual', $hashOriginal)
            ->assertJsonPath('data.hash_firmado', $hashOriginal);
    }

    public function test_validar_returns_integrity_false_when_hash_differs()
    {
        Storage::fake('radicados_recibidos');

        $user = User::factory()->create(['estado' => 1]);
        $file = UploadedFile::fake()->create('documento.pdf', 100);
        $path = $file->store('', 'radicados_recibidos');

        $hashOriginal = hash_file('sha256', Storage::disk('radicados_recibidos')->path($path));

        $documentoId = $this->insertRadicadoRecibido([
            'num_radicado' => 'VAL-INTEGRIDAD-002',
            'archivo_digital' => $path,
            'hash_sha256' => $hashOriginal,
            'estado_firma' => 'firmado',
        ]);

        DB::table('firmas_eventos')->insert([
            'documentable_id' => $documentoId,
            'documentable_type' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
            'user_id' => $user->id,
            'hash_original' => $hashOriginal,
            'hash_firmado' => 'hash_totalmente_diferente',
            'otp_utilizado' => '***123',
            'user_agent' => 'test',
            'ip_address' => '127.0.0.1',
            'fecha_firma' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/transversal/firma-validar', [
            'documentable_type' => 'radicado_recibido',
            'documentable_id' => $documentoId,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valido', false);
    }

    public function test_validar_returns_404_when_document_not_found()
    {
        $user = User::factory()->create(['estado' => 1]);

        $response = $this->actingAs($user)->postJson('/api/transversal/firma-validar', [
            'documentable_type' => 'radicado_recibido',
            'documentable_id' => 999,
        ]);

        $response->assertStatus(404);
    }

    public function test_validar_returns_422_when_type_is_invalid()
    {
        $user = User::factory()->create(['estado' => 1]);

        $response = $this->actingAs($user)->postJson('/api/transversal/firma-validar', [
            'documentable_type' => 'tipo_inexistente',
            'documentable_id' => 1,
        ]);

        $response->assertStatus(422);
    }
}
