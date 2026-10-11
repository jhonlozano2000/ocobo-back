<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M03-03: Internas no tenía filtrado ABAC; cualquier usuario con el permiso de la ruta
 * veía, editaba y descargaba radicados internos de todas las dependencias.
 */
class AccesoRadicadosInternosTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM = 'Radicar -> Cores. Interna -> ';

    private User $user;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();

        // La BD de pruebas puede no tener aplicada la migración que renombra las tablas a plural;
        // una tabla TEMPORARY (de sesión, sin commit implícito) las suple.
        foreach (['responsa', 'proyectores'] as $sufijo) {
            if (! Schema::hasTable("ventanilla_radica_internos_{$sufijo}")) {
                DB::statement("CREATE TEMPORARY TABLE ventanilla_radica_internos_{$sufijo} LIKE ventanilla_radica_interno_{$sufijo}");
            }
        }

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        foreach (['Mostrar', 'Listar', 'Editar', 'Eliminar', 'Actualizar asunto'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.$accion, 'web'));
        }
    }

    private function interno(int $usuarioCrea): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_internos')->insertGetId([
            'num_radicado' => 'M0303-'.uniqid(), 'clasifica_documen_id' => 1, 'usuario_crea' => $usuarioCrea,
            'archivo_digital' => 'radicados_internos/no-existe.pdf',
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function cargoDelUsuario(): int
    {
        Schema::disableForeignKeyConstraints();
        $cargo = DB::table('users_cargos')->insertGetId(['user_id' => $this->user->id, 'cargo_id' => 999999, 'fecha_inicio' => '2026-01-01', 'estado' => 1]);
        Schema::enableForeignKeyConstraints();

        return $cargo;
    }

    private function idsListado(): array
    {
        return collect($this->actingAs($this->user)->getJson('/api/ventanilla/radica-interno?per_page=100')->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_interno_ajeno_responde_404_y_no_se_lista(): void
    {
        $ajeno = $this->interno($this->otro->id);

        foreach (["/api/ventanilla/radica-interno/{$ajeno}", "/api/ventanilla/radica-interno/{$ajeno}/linea-tiempo",
            "/api/ventanilla/radica-interno/{$ajeno}/archivos/digital/download", "/api/ventanilla/radica-interno/{$ajeno}/archivos/adjuntos/1/descargar",
            "/api/ventanilla/radica-interno/{$ajeno}/responsables"] as $url) {
            $this->actingAs($this->user)->getJson($url)->assertNotFound();
        }
        $this->actingAs($this->user)->putJson("/api/ventanilla/radica-interno/{$ajeno}/update-asunto", ['asunto' => 'x'])->assertNotFound();
        $this->assertNotContains($ajeno, $this->idsListado());
    }

    public function test_participantes_y_ver_todos_acceden(): void
    {
        $propio = $this->interno($this->user->id);
        $responsable = $this->interno($this->otro->id);
        $destinatario = $this->interno($this->otro->id);
        $proyector = $this->interno($this->otro->id);
        $firmante = $this->interno($this->otro->id);
        $cargo = $this->cargoDelUsuario();
        DB::table('ventanilla_radica_internos_responsa')->insert(['radica_interno_id' => $responsable, 'users_cargos_id' => $cargo]);
        DB::table('ventanilla_radica_internos_destina')->insert(['radica_interno_id' => $destinatario, 'users_cargos_id' => $cargo]);
        DB::table('ventanilla_radica_internos_proyectores')->insert(['radica_interno_id' => $proyector, 'users_cargos_id' => $cargo]);
        DB::table('ventanilla_radica_internos_firmantes')->insert(['radica_interno_id' => $firmante, 'users_id' => $this->user->id]);

        $visibles = [$propio, $responsable, $destinatario, $proyector, $firmante];
        foreach ($visibles as $id) {
            $this->assertNotSame(404, $this->actingAs($this->user)->getJson("/api/ventanilla/radica-interno/{$id}/linea-tiempo")->status(), "radicado {$id}");
        }
        $this->assertEqualsCanonicalizing($visibles, array_values(array_intersect($this->idsListado(), $visibles)));

        $ajeno = $this->interno($this->otro->id);
        $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.'Ver Todos', 'web'));
        $this->assertNotSame(404, $this->actingAs($this->user)->getJson("/api/ventanilla/radica-interno/{$ajeno}/linea-tiempo")->status());
    }
}
