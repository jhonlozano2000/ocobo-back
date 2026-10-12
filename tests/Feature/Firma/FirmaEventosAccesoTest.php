<?php

namespace Tests\Feature\Firma;

use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M06-12: el indicador de firma ya no afirma integridad (era contradictorio entre el historial
 * y "mis firmas"); el historial y la validación solo los consulta quien tiene acceso al documento.
 */
class FirmaEventosAccesoTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        Permission::findOrCreate('Radicar -> PQRSF -> Listar', 'web');
    }

    private function radicado(int $usuarioCrea): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0612-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $usuarioCrea,
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function evento(int $radicadoId, int $userId, ?string $hashFirmado = null): FirmaEvento
    {
        return FirmaEvento::create([
            'documentable_type' => VentanillaRadicaReci::class,
            'documentable_id' => $radicadoId,
            'user_id' => $userId,
            'hash_original' => str_repeat('a', 64),
            'hash_firmado' => $hashFirmado ?? str_repeat('a', 64), // sin estampar: mismo hash
            'otp_utilizado' => '000000',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'fecha_firma' => now(),
        ]);
    }

    public function test_historial_y_validacion_de_documento_ajeno_responden_403(): void
    {
        $ajeno = $this->radicado($this->otro->id);
        $this->evento($ajeno, $this->otro->id, str_repeat('b', 64));

        $this->actingAs($this->user)->getJson("/api/transversal/firma-eventos/reci/{$ajeno}")->assertForbidden();
        $this->actingAs($this->user)->postJson('/api/transversal/firma-validar', [
            'documentable_type' => 'radicado_recibido', 'documentable_id' => $ajeno,
        ])->assertForbidden();
    }

    public function test_historial_de_documento_propio_o_firmado_por_el_usuario(): void
    {
        $propio = $this->radicado($this->user->id);
        $this->evento($propio, $this->user->id, str_repeat('b', 64));

        $firmas = $this->actingAs($this->user)->getJson("/api/transversal/firma-eventos/reci/{$propio}")
            ->assertOk()->json('data.firmas');
        $this->assertTrue($firmas[0]['estampado']);
        $this->assertFalse($firmas[0]['con_estampa_tiempo']);
        $this->assertArrayNotHasKey('integro', $firmas[0]);

        // Firmante designado en un documento que no es suyo
        $ajeno = $this->radicado($this->otro->id);
        $this->evento($ajeno, $this->user->id);
        $this->actingAs($this->user)->getJson("/api/transversal/firma-eventos/reci/{$ajeno}")->assertOk();
    }

    public function test_mis_firmas_usa_el_mismo_indicador_y_mapea_el_tipo(): void
    {
        $propio = $this->radicado($this->user->id);
        $estampado = $this->evento($propio, $this->user->id, str_repeat('b', 64));
        $sinEstampa = $this->evento($propio, $this->user->id);

        $filas = collect($this->actingAs($this->user)->getJson('/api/transversal/firma-eventos/mis-firmas')
            ->assertOk()->json('data'))->keyBy('id');

        $this->assertTrue($filas[$estampado->id]['estampado']);
        $this->assertFalse($filas[$sinEstampa->id]['estampado']);
        $this->assertSame('reci', $filas[$estampado->id]['documentable_type']);
    }
}
