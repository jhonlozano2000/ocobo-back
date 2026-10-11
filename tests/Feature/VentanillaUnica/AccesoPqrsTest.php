<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M04-01/02: las rutas por id de PQRS no filtraban por ABAC y el bypass del scope de la
 * vista era el mismo permiso que exige el listado.
 */
class AccesoPqrsTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM = 'Radicar -> PQRSF -> ';

    private User $user;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        foreach (['Listar', 'Mostrar', 'Editar', 'Eliminar', 'Actualizar asunto'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.$accion, 'web'));
        }
    }

    /** @return array{0: int, 1: int} [pqrsId, radicadoId] */
    private function pqrs(int $usuarioCrea): array
    {
        Schema::disableForeignKeyConstraints();
        $radicado = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0401-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $usuarioCrea,
        ]);
        $pqrs = DB::table('ventanilla_pqrs')->insertGetId([
            'ventanilla_radica_reci_id' => $radicado, 'tipo_pqrs_id' => 1, 'fecha_vencimiento' => '2026-12-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return [$pqrs, $radicado];
    }

    private function idsListado(string $url): array
    {
        return collect($this->actingAs($this->user)->getJson($url)->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_pqrs_ajena_responde_404_y_no_se_lista(): void
    {
        [$ajena] = $this->pqrs($this->otro->id);

        foreach (["/api/ventanilla/pqrs/{$ajena}", "/api/ventanilla/pqrs/{$ajena}/linea-tiempo", "/api/ventanilla/pqrs/{$ajena}/historial-clasificacion"] as $url) {
            $this->actingAs($this->user)->getJson($url)->assertNotFound();
        }
        $this->actingAs($this->user)->putJson("/api/ventanilla/pqrs/{$ajena}/asunto", ['asunto' => 'x'])->assertNotFound();
        $this->assertNotContains($ajena, $this->idsListado('/api/ventanilla/pqrs?per_page=100'));
    }

    public function test_pqrs_propia_o_con_ver_todos_es_accesible(): void
    {
        [$propia] = $this->pqrs($this->user->id);
        [$ajena] = $this->pqrs($this->otro->id);

        $this->assertNotSame(404, $this->actingAs($this->user)->getJson("/api/ventanilla/pqrs/{$propia}")->status());
        $this->assertContains($propia, $this->idsListado('/api/ventanilla/pqrs?per_page=100'));

        $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.'Ver Todos', 'web'));
        $this->assertNotSame(404, $this->actingAs($this->user)->getJson("/api/ventanilla/pqrs/{$ajena}")->status());
    }

    public function test_responsable_del_radicado_accede_a_la_pqrs(): void
    {
        [$pqrs, $radicado] = $this->pqrs($this->otro->id);
        Schema::disableForeignKeyConstraints();
        $cargo = DB::table('users_cargos')->insertGetId(['user_id' => $this->user->id, 'cargo_id' => 999999, 'fecha_inicio' => '2026-01-01', 'estado' => 1]);
        DB::table('ventanilla_radica_reci_responsa')->insert(['radica_reci_id' => $radicado, 'users_cargos_id' => $cargo, 'custodio' => 0]);
        Schema::enableForeignKeyConstraints();

        $this->assertNotSame(404, $this->actingAs($this->user)->getJson("/api/ventanilla/pqrs/{$pqrs}")->status());
        $this->assertContains($pqrs, $this->idsListado('/api/ventanilla/pqrs/mis-radicados?per_page=100'));
    }

    public function test_eliminacion_masiva_ignora_pqrs_ajenas(): void
    {
        [$ajena] = $this->pqrs($this->otro->id);

        $this->actingAs($this->user)->deleteJson('/api/ventanilla/pqrs', ['ids' => [$ajena]]);

        $this->assertNull(DB::table('ventanilla_pqrs')->where('id', $ajena)->value('deleted_at'));
    }
}
