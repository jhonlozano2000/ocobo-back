<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B06 (diseño de expedientes): el expediente se abre en serie y subserie de la TRD vigente,
 * declara soporte y nivel de acceso (con fundamento si no es público). Los anteriores quedan
 * "por completar" (D6) hasta que se declare lo que les falta.
 */
class ClasificacionAccesoExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $depId;

    private int $serieConSub;

    private int $subserie;

    private int $otraSubserie;

    private int $serieSinSub;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('ofi_archivo_expedientes', 'nivel_acceso')) {
            $this->markTestSkipped('Requiere la migración 2026_10_19_110000.');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Ver Todos', 'Ver', 'Crear', 'Editar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> '.$accion, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $this->depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B06', 'cod_organico' => 'B06D']);
        $version = DB::table('clasificacion_documental_trd_versiones')->insertGetId([
            'dependencia_id' => $this->depId, 'version' => random_int(1000, 9999), 'estado_version' => 'ACTIVO', 'user_register' => $this->user->id,
        ]);
        $nodo = fn (string $tipo, string $cod, ?int $parent = null) => DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => $tipo, 'cod' => $cod, 'nom' => $tipo.' '.$cod, 'parent' => $parent,
            'dependencia_id' => $this->depId, 'version_id' => $version, 'user_register' => $this->user->id,
        ]);
        $this->serieConSub = $nodo('Serie', 'C01');
        $this->subserie = $nodo('SubSerie', 'C01.2', $this->serieConSub);
        $this->serieSinSub = $nodo('Serie', 'C02');
        $this->otraSubserie = $nodo('SubSerie', 'C02.9', $nodo('Serie', 'C03'));
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->user);
    }

    private function abrir(array $datos)
    {
        return $this->postJson('/api/archivo/expedientes', $datos + [
            'nombre_expediente' => 'Contrato de obra', 'dependencia_id' => $this->depId,
            'soporte' => 'hibrido', 'nivel_acceso' => 'publico',
        ]);
    }

    public function test_la_subserie_elegida_en_el_arbol_queda_con_su_serie_y_en_el_numero(): void
    {
        $data = $this->abrir(['serie_trd_id' => $this->subserie])->assertCreated()->json('data');

        $this->assertSame($this->serieConSub, (int) $data['serie_trd_id']);
        $this->assertSame($this->subserie, (int) $data['subserie_trd_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-B06D-C01\.C01\.2-0001$/', $data['numero_expediente']);
        $this->assertSame('hibrido', $data['soporte']);
        $this->assertSame('publico', $data['nivel_acceso']);
        $this->assertFalse((bool) OfiArchivoExpediente::find($data['id'])->por_completar);

        // También vale enviarla en su propio campo junto con la serie
        $this->abrir(['serie_trd_id' => $this->serieConSub, 'subserie_trd_id' => $this->subserie])
            ->assertCreated()->assertJsonPath('data.subserie_trd_id', $this->subserie);
    }

    public function test_una_serie_con_subseries_exige_una_de_ellas(): void
    {
        $this->abrir(['serie_trd_id' => $this->serieConSub])
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'tiene subseries'));

        $this->abrir(['serie_trd_id' => $this->serieConSub, 'subserie_trd_id' => $this->otraSubserie])
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'no pertenece a la serie'));

        $this->abrir(['serie_trd_id' => $this->serieSinSub])->assertCreated()->assertJsonPath('data.subserie_trd_id', null);
    }

    public function test_soporte_y_nivel_de_acceso_con_fundamento(): void
    {
        $this->abrir(['serie_trd_id' => $this->serieSinSub, 'soporte' => 'mixto', 'nivel_acceso' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['soporte', 'nivel_acceso']);

        $this->abrir(['serie_trd_id' => $this->serieSinSub, 'nivel_acceso' => 'reservado'])
            ->assertUnprocessable()->assertJsonValidationErrors('fundamento_acceso');

        $this->abrir([
            'serie_trd_id' => $this->serieSinSub, 'nivel_acceso' => 'reservado',
            'fundamento_acceso' => 'Ley 1712 de 2014, art. 19 literal e',
        ])->assertCreated()->assertJsonPath('data.fundamento_acceso', 'Ley 1712 de 2014, art. 19 literal e');

        // Un expediente público no guarda fundamento aunque llegue
        $id = $this->abrir(['serie_trd_id' => $this->serieSinSub, 'fundamento_acceso' => 'No aplica a públicos'])
            ->assertCreated()->assertJsonPath('data.fundamento_acceso', null)->json('data.id');

        $this->putJson("/api/archivo/expedientes/{$id}", ['fundamento_acceso' => 'Ley 1712 de 2014, art. 18'])
            ->assertUnprocessable();
        $this->putJson("/api/archivo/expedientes/{$id}", ['nivel_acceso' => 'clasificado'])
            ->assertUnprocessable()->assertJsonValidationErrors('fundamento_acceso');
        $this->putJson("/api/archivo/expedientes/{$id}", ['nivel_acceso' => 'clasificado', 'fundamento_acceso' => 'Ley 1712 de 2014, art. 18'])
            ->assertOk()->assertJsonPath('data.nivel_acceso', 'clasificado');
        $this->putJson("/api/archivo/expedientes/{$id}", ['nivel_acceso' => 'publico'])
            ->assertOk()->assertJsonPath('data.fundamento_acceso', null);
    }

    public function test_un_expediente_por_completar_sale_al_declarar_lo_que_le_falta(): void
    {
        // Así quedan los expedientes anteriores a B06 tras la migración (D6)
        $expediente = OfiArchivoExpediente::create([
            'numero_expediente' => '2026-B06D-C01-'.uniqid(), 'nombre_expediente' => 'Anterior', 'anio' => 2026,
            'dependencia_id' => $this->depId, 'serie_trd_id' => $this->serieConSub, 'estado' => 'abierto',
            'fecha_apertura' => '2026-01-10', 'usuario_apertura_id' => $this->user->id, 'por_completar' => true,
        ]);

        $this->getJson("/api/archivo/expedientes/{$expediente->id}")
            ->assertOk()->assertJsonPath('data.pendientes', ['nivel_acceso', 'subserie_trd_id']);
        $ids = collect($this->getJson('/api/archivo/expedientes?por_completar=1&per_page=100')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($expediente->id));

        // Editar otros datos no lo bloquea, y declarar el acceso no basta: falta la subserie
        $this->putJson("/api/archivo/expedientes/{$expediente->id}", ['caja' => 'C-7'])->assertOk();
        $this->putJson("/api/archivo/expedientes/{$expediente->id}", ['nivel_acceso' => 'publico'])
            ->assertOk()->assertJsonPath('data.por_completar', true);

        $this->putJson("/api/archivo/expedientes/{$expediente->id}", ['subserie_trd_id' => $this->subserie])
            ->assertOk()
            ->assertJsonPath('data.por_completar', false)
            ->assertJsonPath('data.subserie_trd_id', $this->subserie)
            ->assertJsonPath('data.serie_trd_id', $this->serieConSub);

        $ids = collect($this->getJson('/api/archivo/expedientes?por_completar=1&per_page=100')->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($expediente->id));
    }
}
