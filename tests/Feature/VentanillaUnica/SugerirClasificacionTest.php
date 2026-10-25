<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 17.6: el botón «Sugerir TRD» de los formularios de radicación busca tipos documentales de la
 * TRD cuyo nombre coincide con palabras del asunto (coincidencia de texto, no IA).
 */
class SugerirClasificacionTest extends TestCase
{
    use DatabaseTransactions;

    private const RUTA = '/api/ventanilla/metadata/sugerir-clasificacion';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        Schema::disableForeignKeyConstraints();
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function trd(string $tipo, string $nom, int $dependencia, ?int $parent = null, array $extra = []): int
    {
        return DB::table('clasificacion_documental_trd')->insertGetId($extra + [
            'tipo' => $tipo, 'cod' => (string) random_int(100, 999), 'nom' => $nom, 'parent' => $parent,
            'dependencia_id' => $dependencia, 'user_register' => $this->user->id, 'estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_sugiere_tipos_documentales_por_el_asunto_con_su_ruta(): void
    {
        $serie = $this->trd('Serie', 'Peticiones', 990001);
        $subserie = $this->trd('SubSerie', 'Derechos de peticion', 990001, $serie);
        $tipo = $this->trd('TipoDocumento', 'Oficio zarquifonia', 990001, $subserie, ['dias_vencimiento' => 15]);
        $this->trd('TipoDocumento', 'Acta zarquifonia', 990001, $subserie, ['estado' => 0]);
        $this->trd('TipoDocumento', 'Informe zarquifonia', 990002, $subserie);

        $datos = $this->actingAs($this->user)
            ->getJson(self::RUTA.'?'.http_build_query(['asunto' => 'Solicitud de zarquifonia urgente', 'dependencia_id' => 990001]))
            ->assertOk()->json('data');

        $this->assertSame(['Oficio zarquifonia'], array_column($datos, 'nom'));
        $this->assertSame($tipo, $datos[0]['id']);
        $this->assertSame('Peticiones', $datos[0]['serie']);
        $this->assertSame('Derechos de peticion', $datos[0]['subserie']);
        $this->assertSame('Peticiones › Derechos de peticion › Oficio zarquifonia', $datos[0]['ruta']);
        $this->assertSame(15, $datos[0]['dias_vencimiento']);
    }

    public function test_sin_palabras_utiles_no_sugiere_nada(): void
    {
        $this->actingAs($this->user)
            ->getJson(self::RUTA.'?asunto='.urlencode('de la por'))
            ->assertOk()->assertJsonPath('data', []);
    }

    public function test_exige_el_asunto(): void
    {
        $this->actingAs($this->user)->getJson(self::RUTA)->assertStatus(422);
    }
}
