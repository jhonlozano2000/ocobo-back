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

    private function nodo(string $tipo, string $nom, ?int $parent = null): int
    {
        return DB::table('calidad_organigrama')->insertGetId([
            'tipo' => $tipo, 'nom_organico' => $nom, 'cod_organico' => 'S'.random_int(10000, 99999), 'parent' => $parent,
        ]);
    }

    private function funcionario(int $cargo, string $nombre, bool $activo = true): int
    {
        $id = User::factory()->create(['estado' => 1, 'nombres' => $nombre, 'apellidos' => 'XSUG'])->id;
        DB::table('users_cargos')->insert([
            'user_id' => $id, 'cargo_id' => $cargo, 'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => $activo ? null : now()->toDateString(), 'estado' => $activo,
        ]);

        return $id;
    }

    // Con la clasificación sugerida se elige el responsable: van los funcionarios de su dependencia
    public function test_cada_sugerencia_trae_los_funcionarios_de_su_dependencia(): void
    {
        $dependencia = $this->nodo('Dependencia', 'Gestion Humana XSUG');
        $oficina = $this->nodo('Oficina', 'Nomina XSUG', $dependencia);
        $hija = $this->nodo('Dependencia', 'Bienestar XSUG', $dependencia);
        $directo = $this->funcionario($this->nodo('Cargo', 'Jefe XSUG', $dependencia), 'Ana');
        $deOficina = $this->funcionario($this->nodo('Cargo', 'Auxiliar XSUG', $oficina), 'Luis');
        $this->funcionario($this->nodo('Cargo', 'Psicologo XSUG', $hija), 'Marta');
        $this->funcionario($this->nodo('Cargo', 'Antiguo XSUG', $dependencia), 'Pedro', false);
        $this->trd('TipoDocumento', 'Contrato zarquifonia', $dependencia);

        $datos = $this->actingAs($this->user)
            ->getJson(self::RUTA.'?asunto='.urlencode('Contrato zarquifonia'))
            ->assertOk()->json('data');

        $this->assertSame(['id' => $dependencia, 'nom_organico' => 'Gestion Humana XSUG'], $datos[0]['dependencia']);
        $funcionarios = collect($datos[0]['funcionarios']);
        $this->assertEqualsCanonicalizing([$directo, $deOficina], $funcionarios->pluck('id')->all());
        // El de la oficina también queda con la dependencia, que es la que se guarda en el responsable
        $this->assertSame($dependencia, $funcionarios->firstWhere('id', $deOficina)['dependencia']['id']);
        $this->assertSame('Auxiliar XSUG', $funcionarios->firstWhere('id', $deOficina)['cargo']['nom_organico']);
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
