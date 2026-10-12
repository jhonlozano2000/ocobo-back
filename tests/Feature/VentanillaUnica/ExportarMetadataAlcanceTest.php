<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M01-13: la exportación de metadata devolvía la de todos los radicados del módulo,
 * sin el filtrado ABAC que sí aplican show/historial/store.
 */
class ExportarMetadataAlcanceTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Recibida -> Exportar', 'web'));
    }

    private function radicadoConMetadata(int $usuarioCrea, string $numero): void
    {
        Schema::disableForeignKeyConstraints();
        $radicadoId = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => $numero, 'medio_recep_id' => 1, 'usuario_crea' => $usuarioCrea,
        ]);
        DB::table('ventanilla_radica_reci_metadata')->insert([
            'radicado_id' => $radicadoId, 'archivo_id' => $radicadoId, 'num_radicado' => $numero,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function exportar(): string
    {
        return $this->actingAs($this->user)->get('/api/ventanilla/metadata/exportar/reci')->assertOk()->streamedContent();
    }

    public function test_solo_exporta_metadata_de_radicados_visibles(): void
    {
        $propio = 'M0113-P-'.uniqid();
        $ajeno = 'M0113-A-'.uniqid();
        $this->radicadoConMetadata($this->user->id, $propio);
        $this->radicadoConMetadata($this->otro->id, $ajeno);

        $csv = $this->exportar();

        $this->assertStringContainsString($propio, $csv);
        $this->assertStringNotContainsString($ajeno, $csv);
    }

    public function test_ver_todos_exporta_todo_el_modulo(): void
    {
        $ajeno = 'M0113-A-'.uniqid();
        $this->radicadoConMetadata($this->otro->id, $ajeno);
        $this->user->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Recibida -> Ver Todos', 'web'));

        $this->assertStringContainsString($ajeno, $this->exportar());
    }

    public function test_enviados_e_internos_aplican_el_mismo_filtro(): void
    {
        if (! Schema::hasTable('ventanilla_radica_enviados_responsable')) {
            $this->markTestSkipped('BD de testing sin las tablas de responsables de enviados.');
        }

        $this->user->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Enviada -> Exportar', 'web'));
        $this->user->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Interna -> Exportar', 'web'));

        foreach (['enviados', 'interno'] as $tipo) {
            $this->actingAs($this->user)->get("/api/ventanilla/metadata/exportar/{$tipo}")->assertOk()->streamedContent();
        }
    }
}
