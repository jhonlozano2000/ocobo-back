<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M01-01/05 y M12-01: las rutas por id de Recibidas no aplicaban el filtrado ABAC que sí
 * usa el listado.
 */
class AccesoRadicadosRecibidosTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM = 'Radicar -> Cores. Recibida -> ';

    private User $user;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        // Existe en producción; el scope de la vista PQRS la consulta
        Permission::findOrCreate('Radicar -> PQRSF -> Listar', 'web');
        foreach (['Mostrar', 'Listar', 'Editar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.$accion, 'web'));
        }
    }

    private function radicado(int $usuarioCrea, string $ocr = 'texto'): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0101-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $usuarioCrea, 'ocr' => $ocr,
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    public function test_radicado_ajeno_responde_404_en_rutas_por_id(): void
    {
        $ajeno = $this->radicado($this->otro->id);

        foreach (["/api/ventanilla/radica-recibida/{$ajeno}", "/api/ventanilla/radica-recibida/{$ajeno}/linea-tiempo",
            "/api/ventanilla/radica-recibida/{$ajeno}/archivos/digital/ocr", "/api/ventanilla/radica-recibida/{$ajeno}/archivos/adjuntos/listar"] as $url) {
            $this->actingAs($this->user)->getJson($url)->assertNotFound();
        }
        $this->actingAs($this->user)->putJson("/api/ventanilla/radica-recibida/{$ajeno}", ['asunto' => 'x'])->assertNotFound();
    }

    public function test_radicado_propio_o_con_ver_todos_es_accesible(): void
    {
        $propio = $this->radicado($this->user->id);
        $ajeno = $this->radicado($this->otro->id);

        $this->assertNotSame(404, $this->actingAs($this->user)->getJson("/api/ventanilla/radica-recibida/{$propio}/linea-tiempo")->status());

        $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.'Ver Todos', 'web'));
        $this->assertNotSame(404, $this->actingAs($this->user)->getJson("/api/ventanilla/radica-recibida/{$ajeno}/linea-tiempo")->status());
    }

    public function test_responsable_asignado_accede_al_radicado(): void
    {
        $ajeno = $this->radicado($this->otro->id);
        Schema::disableForeignKeyConstraints();
        $cargo = DB::table('users_cargos')->insertGetId(['user_id' => $this->user->id, 'cargo_id' => 999999, 'fecha_inicio' => '2026-01-01', 'estado' => 1]);
        DB::table('ventanilla_radica_reci_responsa')->insert(['radica_reci_id' => $ajeno, 'users_cargos_id' => $cargo, 'custodio' => 0]);
        Schema::enableForeignKeyConstraints();

        $this->assertNotSame(404, $this->actingAs($this->user)->getJson("/api/ventanilla/radica-recibida/{$ajeno}/linea-tiempo")->status());
    }

    public function test_busqueda_ocr_solo_devuelve_radicados_visibles(): void
    {
        $propio = $this->radicado($this->user->id, 'contrato reservado M0101');
        $ajeno = $this->radicado($this->otro->id, 'contrato reservado M0101');

        $ids = collect($this->actingAs($this->user)
            ->getJson('/api/ventanilla/radica-recibida/search/ocr?searchOcr=reservado M0101')
            ->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($propio));
        $this->assertFalse($ids->contains($ajeno));
    }
}
