<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * En Enviados las fechas solo filtraban con ambas (y "hasta" excluía ese día), el responsable esperaba un id
 * aunque la vista manda un nombre, y la búsqueda/OCR iba a /search/ocr descartando los demás filtros.
 */
class FiltrosRadicadosEnviadosTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Enviada -> Listar', 'web'));
    }

    private function enviado(string $asunto, array $extra = []): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_enviados')->insertGetId(array_merge([
            'num_radicado' => 'FEN-'.uniqid(), 'clasifica_documen_id' => 1, 'medio_enviado_id' => 1,
            'tercero_id' => 1, 'tipo_respuesta_id' => 1, 'usuario_crea' => $this->user->id, 'asunto' => $asunto,
            'hash_sha256' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now(),
        ], $extra));
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function asuntos(string $query, string $search = 'FENTEST'): array
    {
        $respuesta = $this->actingAs($this->user)
            ->getJson("/api/ventanilla/radica-enviada?per_page=100&search={$search}&".$query)->assertOk()->json();

        $asuntos = array_column($respuesta['data']['data'] ?? $respuesta['data'], 'asunto');
        sort($asuntos);

        return $asuntos;
    }

    public function test_fechas_por_separado_y_hasta_inclusivo(): void
    {
        $this->enviado('FENTEST viejo', ['created_at' => '2026-01-10 09:00:00']);
        $this->enviado('FENTEST nuevo', ['created_at' => '2026-03-20 17:30:00']);

        $this->assertSame(['FENTEST nuevo'], $this->asuntos('fecha_desde=2026-02-01'));
        $this->assertSame(['FENTEST viejo'], $this->asuntos('fecha_hasta=2026-01-10'));
        $this->assertSame(['FENTEST nuevo'], $this->asuntos('fecha_desde=2026-03-20&fecha_hasta=2026-03-20'));
    }

    public function test_ocr_combinado_y_busqueda_general_en_el_contenido(): void
    {
        Schema::disableForeignKeyConstraints();
        $medio = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => 'Correo FENTEST']);
        $otroMedio = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => 'Físico FENTEST']);
        Schema::enableForeignKeyConstraints();
        $this->enviado('FENTEST con ocr', ['ocr' => 'oficio zqxenv de respuesta', 'medio_enviado_id' => $medio]);
        $this->enviado('FENTEST otro medio', ['ocr' => 'oficio zqxenv', 'medio_enviado_id' => $otroMedio]);

        $this->assertSame(['FENTEST con ocr', 'FENTEST otro medio'], $this->asuntos('ocr=zqxenv'));
        $this->assertSame(['FENTEST con ocr'], $this->asuntos("ocr=zqxenv&medio_enviado_id={$medio}"));
        $this->assertSame(['FENTEST con ocr', 'FENTEST otro medio'], $this->asuntos('', 'zqxenv'));
    }

    public function test_clasificacion_por_serie_incluye_sus_tipos_documentales(): void
    {
        Schema::disableForeignKeyConstraints();
        $trd = fn (string $tipo, ?int $parent = null) => DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => $tipo, 'cod' => 'FEN'.random_int(100, 999), 'nom' => "{$tipo} FENTEST", 'parent' => $parent,
            'user_register' => $this->user->id, 'estado' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $serie = $trd('Serie');
        $subserie = $trd('SubSerie', $serie);
        $otraSerie = $trd('Serie');

        $this->enviado('FENTEST en subserie', ['clasifica_documen_id' => $trd('TipoDocumento', $subserie)]);
        $this->enviado('FENTEST en serie', ['clasifica_documen_id' => $trd('TipoDocumento', $serie)]);
        $this->enviado('FENTEST otra serie', ['clasifica_documen_id' => $trd('TipoDocumento', $otraSerie)]);
        Schema::enableForeignKeyConstraints();

        $this->assertSame(['FENTEST en serie', 'FENTEST en subserie'], $this->asuntos("clasifica_documen_id={$serie}"));
        $this->assertSame(['FENTEST en subserie'], $this->asuntos("clasifica_documen_id={$subserie}"));

        $this->actingAs($this->user)->getJson('/api/ventanilla/radica-enviada/clasificaciones')->assertOk()
            ->assertJsonFragment(['id' => $subserie, 'tipo' => 'SubSerie', 'nom' => 'SubSerie FENTEST'])
            ->assertJsonMissing(['nom' => 'TipoDocumento FENTEST']);
    }

    public function test_responsable_por_nombre(): void
    {
        $cargo = DB::table('calidad_organigrama')->insertGetId([
            'tipo' => 'Cargo', 'nom_organico' => 'Cargo FENTEST', 'cod_organico' => 'E'.random_int(1000, 9999),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $responsable = User::factory()->create(['nombres' => 'Zoraida', 'apellidos' => 'Quintero']);
        $userCargo = DB::table('users_cargos')->insertGetId([
            'user_id' => $responsable->id, 'cargo_id' => $cargo, 'fecha_inicio' => now()->toDateString(),
            'estado' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $asignado = $this->enviado('FENTEST asignado');
        $this->enviado('FENTEST sin asignar');
        DB::table('ventanilla_radica_enviados_responsable')->insert([
            'radica_enviado_id' => $asignado, 'users_cargos_id' => $userCargo, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(['FENTEST asignado'], $this->asuntos('usuario_responsable=zoraida quin'));
    }
}
