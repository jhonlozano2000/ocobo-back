<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * RBAC-003: /api/ventanilla/metadata/* solo exigía el permiso del módulo; con él se leía o
 * editaba la metadata de archivos de cualquier radicado conociendo el archivoId.
 */
class MetadataAccesoRadicadoTest extends TestCase
{
    use DatabaseTransactions;

    private User $creador;

    private User $ajeno;

    private int $archivoId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Mostrar', 'Editar'] as $accion) {
            Permission::findOrCreate("Radicar -> Cores. Recibida -> {$accion}", 'web');
        }
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->ajeno = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo(['Radicar -> Cores. Recibida -> Mostrar', 'Radicar -> Cores. Recibida -> Editar']);
        $this->ajeno->givePermissionTo(['Radicar -> Cores. Recibida -> Mostrar', 'Radicar -> Cores. Recibida -> Editar']);

        $this->archivoId = random_int(900000000, 999999999);

        Schema::disableForeignKeyConstraints();
        $radicadoId = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'RBAC3M-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->creador->id,
            'asunto' => 'Metadata reservada',
        ]);
        DB::table('ventanilla_radica_reci_metadata')->insert([
            'archivo_id' => $this->archivoId, 'radicado_id' => $radicadoId, 'descripcion' => 'original',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();
    }

    public function test_solo_quien_accede_al_radicado_lee_la_metadata(): void
    {
        $this->actingAs($this->creador)->getJson("/api/ventanilla/metadata/archivos/{$this->archivoId}/reci")->assertOk();
        $this->actingAs($this->ajeno)->getJson("/api/ventanilla/metadata/archivos/{$this->archivoId}/reci")->assertNotFound();
        $this->actingAs($this->ajeno)->getJson("/api/ventanilla/metadata/historial/{$this->archivoId}/reci")->assertNotFound();
    }

    public function test_un_ajeno_no_edita_la_metadata(): void
    {
        $this->actingAs($this->ajeno)
            ->putJson("/api/ventanilla/metadata/archivos/{$this->archivoId}/reci", ['descripcion' => 'alterada'])
            ->assertNotFound();

        $this->assertDatabaseHas('ventanilla_radica_reci_metadata', ['archivo_id' => $this->archivoId, 'descripcion' => 'original']);
    }

    public function test_la_metadata_de_pqrs_exige_acceso_a_la_pqrs(): void
    {
        Permission::findOrCreate('Radicar -> PQRSF -> Mostrar', 'web');
        $this->creador->givePermissionTo('Radicar -> PQRSF -> Mostrar');
        $this->ajeno->givePermissionTo('Radicar -> PQRSF -> Mostrar');
        $archivoId = $this->archivoId - 1;

        Schema::disableForeignKeyConstraints();
        $radicadoId = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M1705M-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->creador->id,
        ]);
        $pqrsId = DB::table('ventanilla_pqrs')->insertGetId([
            'ventanilla_radica_reci_id' => $radicadoId, 'tipo_pqrs_id' => 1, 'fecha_vencimiento' => '2026-12-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ventanilla_pqrs_metadata')->insert([
            'archivo_id' => $archivoId, 'pqrs_id' => $pqrsId, 'descripcion' => 'original',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->creador)->getJson("/api/ventanilla/metadata/archivos/{$archivoId}/pqrs")->assertOk()
            ->assertJsonPath('data.metadata.descripcion', 'original');
        $this->actingAs($this->ajeno)->getJson("/api/ventanilla/metadata/archivos/{$archivoId}/pqrs")->assertNotFound();
        $this->actingAs($this->ajeno)->getJson("/api/ventanilla/metadata/historial/{$archivoId}/pqrs")->assertNotFound();
    }

    public function test_el_centinela_cero_de_los_digitales_no_es_direccionable(): void
    {
        Schema::disableForeignKeyConstraints();
        $radicadoId = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'SENT0-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->creador->id,
            'asunto' => 'Digital',
        ]);
        DB::table('ventanilla_radica_reci_metadata')->insert([
            'archivo_id' => 0, 'radicado_id' => $radicadoId, 'tipo_archivo' => 'digital', 'descripcion' => 'original',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->creador)->getJson('/api/ventanilla/metadata/archivos/0/reci')->assertNotFound();
        $this->actingAs($this->creador)->getJson('/api/ventanilla/metadata/historial/0/reci')->assertNotFound();
        $this->actingAs($this->creador)
            ->putJson('/api/ventanilla/metadata/archivos/0/reci', ['descripcion' => 'alterada'])
            ->assertNotFound();

        $this->assertDatabaseHas('ventanilla_radica_reci_metadata', ['radicado_id' => $radicadoId, 'descripcion' => 'original']);
    }
}
