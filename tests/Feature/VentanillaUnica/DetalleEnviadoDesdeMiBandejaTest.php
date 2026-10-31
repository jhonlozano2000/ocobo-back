<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * El responsable abre el detalle del enviado desde Mi Bandeja sin los permisos de Ventanilla:
 * antes respondía 403 y no podía firmarlo. acceso_radicado sigue limitando a lo suyo.
 */
class DetalleEnviadoDesdeMiBandejaTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ventanilla_radica_enviados_responsable')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_enviados_responsable LIKE ventanilla_radica_enviados_responsa');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('Mi Bandeja - Enviados -> Ver', 'web'));
    }

    private function enviado(): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => 'MB-'.uniqid(), 'clasifica_documen_id' => 1, 'medio_enviado_id' => 1,
            'tercero_id' => 1, 'tipo_respuesta_id' => 1, 'usuario_crea' => User::factory()->create()->id,
            'archivo_digital' => 'radicados_enviados/no-existe.pdf', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function rutasDeLectura(int $id): array
    {
        return [
            "/api/ventanilla/radica-enviada/{$id}",
            "/api/ventanilla/radica-enviada/{$id}/linea-tiempo",
            "/api/ventanilla/radica-enviada/{$id}/archivos/adjuntos/listar",
            "/api/ventanilla/radica-enviada/{$id}/responsables",
            "/api/ventanilla/radica-enviada/{$id}/firmantes",
            "/api/ventanilla/radica-enviada/{$id}/proyectores",
            "/api/ventanilla/radica-enviada/{$id}/recibidos",
        ];
    }

    public function test_el_responsable_ve_el_detalle_con_el_permiso_de_mi_bandeja(): void
    {
        $id = $this->enviado();
        Schema::disableForeignKeyConstraints();
        $cargo = DB::table('users_cargos')->insertGetId(['user_id' => $this->user->id, 'cargo_id' => 999999, 'fecha_inicio' => '2026-01-01', 'estado' => 1]);
        Schema::enableForeignKeyConstraints();
        DB::table('ventanilla_radica_enviados_responsable')->insert(['radica_enviado_id' => $id, 'users_cargos_id' => $cargo, 'custodio' => 0]);

        foreach ($this->rutasDeLectura($id) as $url) {
            $this->assertSame(200, $this->actingAs($this->user)->getJson($url)->status(), $url);
        }
        // Editar sigue exigiendo el permiso de Ventanilla
        $this->actingAs($this->user)->putJson("/api/ventanilla/radica-enviada/{$id}/update-asunto", ['asunto' => 'x'])->assertForbidden();
    }

    public function test_un_enviado_ajeno_sigue_oculto(): void
    {
        $id = $this->enviado();

        foreach ($this->rutasDeLectura($id) as $url) {
            $this->actingAs($this->user)->getJson($url)->assertNotFound();
        }
    }
}
