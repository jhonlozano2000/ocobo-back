<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Mi Bandeja (Enviados e Internos) lista también los radicados donde el usuario es firmante;
 * antes solo los de responsable y el firmante no los encontraba.
 */
class MiBandejaFirmantesTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $cargo;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ventanilla_radica_enviados_responsable')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_enviados_responsable LIKE ventanilla_radica_enviados_responsa');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Mi Bandeja - Enviados -> Ver', 'Mi Bandeja - Internos -> Ver'] as $permiso) {
            $this->user->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $this->cargo = DB::table('users_cargos')->insertGetId(['user_id' => $this->user->id, 'cargo_id' => 999999, 'fecha_inicio' => '2026-01-01', 'estado' => 1]);
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function enviado(): int
    {
        return DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => 'MBF-'.uniqid(), 'clasifica_documen_id' => 1, 'medio_enviado_id' => 1,
            'tercero_id' => 1, 'tipo_respuesta_id' => 1, 'usuario_crea' => User::factory()->create()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function interno(): int
    {
        return DB::table('ventanilla_radica_internos')->insertGetId([
            'num_radicado' => 'MBF-'.uniqid(), 'clasifica_documen_id' => 1,
            'usuario_crea' => User::factory()->create()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function idsListados(string $modulo): array
    {
        return collect($this->actingAs($this->user)->getJson("/api/mi-bandeja/{$modulo}/mis-radicados?per_page=100")
            ->assertOk()->json('data'))->pluck('id')->all();
    }

    private function total(string $modulo): int
    {
        return $this->actingAs($this->user)->getJson("/api/mi-bandeja/{$modulo}/mis-radicados/estadisticas")
            ->assertOk()->json('data.total');
    }

    public function test_enviados_lista_y_cuenta_los_de_firmante(): void
    {
        $antes = $this->total('enviados');
        $firmante = $this->enviado();
        $responsable = $this->enviado();
        $ajeno = $this->enviado();
        DB::table('ventanilla_radica_enviados_firmas')->insert(['radica_enviado_id' => $firmante, 'users_cargos_id' => $this->cargo]);
        DB::table('ventanilla_radica_enviados_responsable')->insert(['radica_enviado_id' => $responsable, 'users_cargos_id' => $this->cargo, 'custodio' => 0]);

        $ids = $this->idsListados('enviados');

        $this->assertContains($firmante, $ids);
        $this->assertContains($responsable, $ids);
        $this->assertNotContains($ajeno, $ids);
        $this->assertSame($antes + 2, $this->total('enviados'));
    }

    public function test_internos_lista_y_cuenta_los_de_firmante(): void
    {
        $antes = $this->total('internos');
        $firmante = $this->interno();
        $responsable = $this->interno();
        $ajeno = $this->interno();
        DB::table('ventanilla_radica_internos_firmantes')->insert(['radica_interno_id' => $firmante, 'users_id' => $this->user->id]);
        DB::table('ventanilla_radica_internos_responsa')->insert(['radica_interno_id' => $responsable, 'users_cargos_id' => $this->cargo]);

        $ids = $this->idsListados('internos');

        $this->assertContains($firmante, $ids);
        $this->assertContains($responsable, $ids);
        $this->assertNotContains($ajeno, $ids);
        $this->assertSame($antes + 2, $this->total('internos'));
    }
}
