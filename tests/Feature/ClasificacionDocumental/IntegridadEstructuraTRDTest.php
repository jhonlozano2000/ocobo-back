<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M09-02: la jerarquía Serie > SubSerie > TipoDocumento se valida en cualquier escritura.
 * M09-03: el código es único entre hermanos (dependencia, versión y padre), no en toda la dependencia.
 */
class IntegridadEstructuraTRDTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $dep;

    private int $otraDep;

    private int $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->actingAs($this->user);
        $this->dep = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep M09', 'cod_organico' => 'M09'.random_int(100, 999)]);
        $this->otraDep = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep M09 B', 'cod_organico' => 'M9B'.random_int(100, 999)]);
        Schema::disableForeignKeyConstraints();
        $this->version = DB::table('clasificacion_documental_trd_versiones')->insertGetId([
            'dependencia_id' => $this->dep, 'version' => random_int(1000, 9999), 'estado_version' => 'TEMP', 'user_register' => $this->user->id,
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function nodo(string $tipo, ?string $cod, ?int $parent = null, array $extra = []): ClasificacionDocumentalTRD
    {
        return ClasificacionDocumentalTRD::create($extra + [
            'tipo' => $tipo, 'cod' => $cod, 'nom' => $tipo.' '.$cod, 'parent' => $parent,
            'dependencia_id' => $this->dep, 'version_id' => $this->version, 'user_register' => $this->user->id,
        ]);
    }

    private function assertRechaza(string $campo, callable $accion): void
    {
        try {
            $accion();
            $this->fail("Se esperaba un error de validación en {$campo}");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());
        }
    }

    public function test_jerarquia_invalida_se_rechaza_al_guardar(): void
    {
        $serie = $this->nodo('Serie', '100');
        $tipoDoc = $this->nodo('TipoDocumento', null, $serie->id);

        $this->assertRechaza('parent', fn () => $this->nodo('Serie', '200', $serie->id));
        $this->assertRechaza('parent', fn () => $this->nodo('SubSerie', '01'));
        $this->assertRechaza('parent', fn () => $this->nodo('SubSerie', '01', $tipoDoc->id));
        $this->assertRechaza('parent', fn () => $this->nodo('TipoDocumento', null, $serie->id, ['dependencia_id' => $this->otraDep]));
        $this->assertRechaza('parent', fn () => $this->nodo('TipoDocumento', null, $serie->id, ['version_id' => null]));
        $this->assertRechaza('tipo', fn () => $serie->update(['tipo' => 'SubSerie']));
    }

    public function test_codigo_unico_entre_hermanos(): void
    {
        $serie = $this->nodo('Serie', '100');
        $otraSerie = $this->nodo('Serie', '200');
        $this->nodo('SubSerie', '01', $serie->id);

        $this->assertRechaza('cod', fn () => $this->nodo('Serie', '100'));
        $this->assertRechaza('cod', fn () => $this->nodo('SubSerie', '01', $serie->id));

        // El mismo código en otro padre u otra versión sí se permite
        $this->nodo('SubSerie', '01', $otraSerie->id);
        $this->nodo('Serie', '100', null, ['version_id' => null]);
        $this->assertSame(2, ClasificacionDocumentalTRD::where('dependencia_id', $this->dep)->where('cod', '01')->count());
    }

    public function test_api_responde_422_con_el_mensaje(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('TRD -> Crear', 'web'));
        $serie = $this->nodo('Serie', '300');
        $this->nodo('SubSerie', '05', $serie->id);

        $this->postJson('/api/clasifica-documental/trd', [
            'tipo' => 'SubSerie', 'cod' => '05', 'nom' => 'Repetida', 'dependencia_id' => $this->dep, 'parent' => $serie->id,
        ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'código ya existe'));
    }
}
