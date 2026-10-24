<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M02-01/02/03: Enviadas no tenía filtrado ABAC; cualquier usuario con el permiso de la ruta
 * veía, editaba y descargaba radicados de toda la entidad.
 */
class AccesoRadicadosEnviadosTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM = 'Radicar -> Cores. Enviada -> ';

    private User $user;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();

        // La BD de pruebas puede no tener aplicada la migración que renombra la tabla de
        // responsables; una tabla TEMPORARY (de sesión, sin commit implícito) la suple.
        if (! Schema::hasTable('ventanilla_radica_enviados_responsable')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_enviados_responsable LIKE ventanilla_radica_enviados_responsa');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        foreach (['Mostrar', 'Listar', 'Editar', 'Eliminar', 'Actualizar asunto'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.$accion, 'web'));
        }
    }

    private function enviado(int $usuarioCrea): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => 'M0201-'.uniqid(), 'clasifica_documen_id' => 1, 'medio_enviado_id' => 1,
            'tercero_id' => 1, 'tipo_respuesta_id' => 1, 'usuario_crea' => $usuarioCrea,
            'archivo_digital' => 'radicados_enviados/no-existe.pdf',
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
        return collect($this->actingAs($this->user)->getJson('/api/ventanilla/radica-enviada?per_page=100')->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_enviado_ajeno_responde_404_y_no_se_lista(): void
    {
        $ajeno = $this->enviado($this->otro->id);

        foreach (["/api/ventanilla/radica-enviada/{$ajeno}", "/api/ventanilla/radica-enviada/{$ajeno}/linea-tiempo",
            "/api/ventanilla/radica-enviada/{$ajeno}/archivos/digital/download", "/api/ventanilla/radica-enviada/{$ajeno}/archivos/adjuntos/1/descargar"] as $url) {
            $this->actingAs($this->user)->getJson($url)->assertNotFound();
        }
        $this->actingAs($this->user)->putJson("/api/ventanilla/radica-enviada/{$ajeno}/update-asunto", ['asunto' => 'x'])->assertNotFound();
        $this->assertNotContains($ajeno, $this->idsListado());
    }

    public function test_creador_firmante_proyector_responsable_y_ver_todos_acceden(): void
    {
        $propio = $this->enviado($this->user->id);
        $firmado = $this->enviado($this->otro->id);
        $proyectado = $this->enviado($this->otro->id);
        $asignado = $this->enviado($this->otro->id);
        $cargo = $this->cargoDelUsuario();
        DB::table('ventanilla_radica_enviados_firmas')->insert(['radica_enviado_id' => $firmado, 'users_cargos_id' => $cargo]);
        DB::table('ventanilla_radica_enviados_proyectores')->insert(['radica_enviado_id' => $proyectado, 'users_cargos_id' => $cargo]);
        DB::table('ventanilla_radica_enviados_responsable')->insert(['radica_enviado_id' => $asignado, 'users_cargos_id' => $cargo, 'custodio' => 0]);

        foreach ([$propio, $firmado, $proyectado, $asignado] as $id) {
            $this->assertNotSame(404, $this->actingAs($this->user)->getJson("/api/ventanilla/radica-enviada/{$id}/linea-tiempo")->status(), "radicado {$id}");
        }
        $this->assertEqualsCanonicalizing([$propio, $firmado, $proyectado, $asignado], array_values(array_intersect($this->idsListado(), [$propio, $firmado, $proyectado, $asignado])));

        $ajeno = $this->enviado($this->otro->id);
        $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.'Ver Todos', 'web'));
        $this->assertNotSame(404, $this->actingAs($this->user)->getJson("/api/ventanilla/radica-enviada/{$ajeno}/linea-tiempo")->status());
    }
}
