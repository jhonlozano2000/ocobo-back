<?php

namespace Tests\Feature\Gestion;

use App\Models\Gestion\GestionTercero;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M16-01 / M16-02: terceros exige permiso (gestión o radicación) y el listado
 * siempre es paginado.
 */
class TercerosAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private const PREFIX = '/api/gestion/terceros';

    protected User $funcionario;

    protected GestionTercero $tercero;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'Gestión - Terceros -> Listar',
            'Gestión - Terceros -> Eliminar',
            'Radicar -> Cores. Recibida -> Crear',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->funcionario = User::factory()->create(['estado' => 1]);
        $this->tercero = GestionTercero::create([
            'num_docu_nit' => 'TST-M16-001',
            'nom_razo_soci' => 'Tercero Prueba M16',
            'tipo' => 'Natural',
        ]);
    }

    public function test_usuario_sin_permiso_no_accede_a_terceros(): void
    {
        $this->actingAs($this->funcionario)->getJson(self::PREFIX)->assertForbidden();
        $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/'.$this->tercero->id)->assertForbidden();
        $this->actingAs($this->funcionario)
            ->postJson(self::PREFIX, ['num_docu_nit' => 'X1', 'nom_razo_soci' => 'X', 'tipo' => 'Natural'])
            ->assertForbidden();
        $this->actingAs($this->funcionario)->deleteJson(self::PREFIX.'/'.$this->tercero->id)->assertForbidden();
    }

    public function test_radicador_puede_buscar_terceros_pero_no_eliminarlos(): void
    {
        $this->funcionario->givePermissionTo('Radicar -> Cores. Recibida -> Crear');

        $this->actingAs($this->funcionario)->getJson(self::PREFIX.'?search=TST-M16-001')->assertOk();
        $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/'.$this->tercero->id)->assertOk();
        $this->actingAs($this->funcionario)->deleteJson(self::PREFIX.'/'.$this->tercero->id)->assertForbidden();
        $this->actingAs($this->funcionario)->getJson('/api/gestion/terceros-estadistica')->assertForbidden();
    }

    public function test_listado_siempre_paginado_y_con_tope(): void
    {
        $this->funcionario->givePermissionTo('Gestión - Terceros -> Listar');

        $sinPerPage = $this->actingAs($this->funcionario)->getJson(self::PREFIX)->assertOk();
        $this->assertSame(20, $sinPerPage->json('pagination.per_page'));

        $enorme = $this->actingAs($this->funcionario)->getJson(self::PREFIX.'?per_page=9999')->assertOk();
        $this->assertSame(100, $enorme->json('pagination.per_page'));
    }

    /**
     * M16-05: un tercero con radicados no se elimina (409 sin detalle SQL); uno sin uso sí.
     */
    public function test_tercero_con_radicados_no_se_elimina(): void
    {
        $this->funcionario->givePermissionTo('Gestión - Terceros -> Eliminar');
        Schema::disableForeignKeyConstraints();
        DB::table('ventanilla_radica_reci')->insert([
            'num_radicado' => 'M1605-'.uniqid(), 'medio_recep_id' => 1,
            'usuario_crea' => $this->funcionario->id, 'tercero_id' => $this->tercero->id,
        ]);
        Schema::enableForeignKeyConstraints();

        $respuesta = $this->actingAs($this->funcionario)->deleteJson(self::PREFIX.'/'.$this->tercero->id)
            ->assertStatus(409)
            ->assertJsonMissingPath('errors');
        $this->assertStringContainsString('1 radicados recibidos', $respuesta->json('message'));
        $this->assertNotNull(GestionTercero::find($this->tercero->id));

        $libre = GestionTercero::create(['num_docu_nit' => 'TST-M16-002', 'nom_razo_soci' => 'Libre', 'tipo' => 'Natural']);
        $this->actingAs($this->funcionario)->deleteJson(self::PREFIX.'/'.$libre->id)->assertOk();
        $this->assertNull(GestionTercero::find($libre->id));
    }
}
