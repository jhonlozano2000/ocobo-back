<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 17.3: al volver a subir el documento digital, el diálogo precarga la descripción y las palabras clave.
 * El digital se guarda con archivo_id = 0, así que se consulta por radicado.
 */
class MetadataDigitalTest extends TestCase
{
    use DatabaseTransactions;

    private User $creador;

    private User $ajeno;

    private int $enviado;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Radicar -> Cores. Enviada -> Mostrar', 'web');
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->ajeno = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo('Radicar -> Cores. Enviada -> Mostrar');
        $this->ajeno->givePermissionTo('Radicar -> Cores. Enviada -> Mostrar');

        Schema::disableForeignKeyConstraints();
        $this->enviado = DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => 'XMETD-'.uniqid(), 'clasifica_documen_id' => 1, 'tercero_id' => 999999,
            'medio_enviado_id' => 999999, 'tipo_respuesta_id' => 999999, 'usuario_crea' => $this->creador->id,
        ]);
        DB::table('ventanilla_radica_enviados_metadata')->insert([
            // Subida anterior del mismo digital: cada subida crea una fila y vale la última
            ['archivo_id' => 0, 'radicado_id' => $this->enviado, 'descripcion' => 'Subida anterior', 'palabras_clave' => null, 'created_at' => now(), 'updated_at' => now()],
            ['archivo_id' => 0, 'radicado_id' => $this->enviado, 'descripcion' => 'Prueba de firma', 'palabras_clave' => json_encode(['uno', 'dos']), 'created_at' => now(), 'updated_at' => now()],
            // Digital de otro radicado: comparte el centinela 0 y no debe filtrarse
            ['archivo_id' => 0, 'radicado_id' => $this->enviado + 1000000, 'descripcion' => 'Otro radicado', 'palabras_clave' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        Schema::enableForeignKeyConstraints();
    }

    public function test_devuelve_la_metadata_del_digital_del_radicado(): void
    {
        $this->actingAs($this->creador)->getJson("/api/ventanilla/metadata/digital/{$this->enviado}/enviados")
            ->assertOk()
            ->assertJsonPath('data.descripcion', 'Prueba de firma')
            ->assertJsonPath('data.palabras_clave', ['uno', 'dos']);
    }

    public function test_sin_acceso_al_radicado_responde_404(): void
    {
        $this->actingAs($this->ajeno)->getJson("/api/ventanilla/metadata/digital/{$this->enviado}/enviados")->assertNotFound();
    }

    public function test_exige_el_permiso_del_tipo(): void
    {
        $this->actingAs($this->creador)->getJson("/api/ventanilla/metadata/digital/{$this->enviado}/reci")->assertForbidden();
        $this->actingAs($this->creador)->getJson("/api/ventanilla/metadata/digital/{$this->enviado}/pqrs")->assertStatus(422);
    }
}
