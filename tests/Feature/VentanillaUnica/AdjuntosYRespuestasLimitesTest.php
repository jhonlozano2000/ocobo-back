<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\RadicadoRespuesta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M01-15/16: el listado de adjuntos exponía la ruta interna y consultaba el disco por fila
 * (un archivo ausente tumbaba el listado completo).
 * M01-17: el contenido de las respuestas no tenía tope y update no validaba nada.
 */
class AdjuntosYRespuestasLimitesTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $radicadoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Mostrar', 'Crear', 'Editar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Recibida -> '.$accion, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $this->radicadoId = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0115-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
        ]);
        Schema::enableForeignKeyConstraints();
    }

    public function test_listado_de_adjuntos_sin_ruta_y_sin_consultar_el_disco(): void
    {
        Storage::fake('radicados_recibidos'); // el archivo no existe en disco
        DB::table('ventanilla_radica_reci_archivos')->insert([
            'radicado_id' => $this->radicadoId, 'subido_por' => $this->user->id, 'archivo' => '2026/10/no-existe.pdf',
            'archivo_tipo' => 'application/pdf', 'nom_origi' => 'oficio.pdf', 'archivo_peso' => 1234,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $archivo = $this->actingAs($this->user)
            ->getJson("/api/ventanilla/radica-recibida/{$this->radicadoId}/archivos/adjuntos/listar")
            ->assertOk()
            ->json('data.0');

        $this->assertArrayNotHasKey('ruta', $archivo);
        $this->assertSame('oficio.pdf', $archivo['nombre']);
        $this->assertSame(1234, $archivo['tamaño']);
        $this->assertSame('application/pdf', $archivo['tipo']);
    }

    public function test_contenido_de_respuesta_con_tope_en_crear_y_editar(): void
    {
        $grande = ['type' => 'doc', 'texto' => str_repeat('a', 2_000_001)];

        $this->actingAs($this->user)
            ->postJson("/api/ventanilla/radica-recibida/{$this->radicadoId}/respuestas", ['contenido_json' => $grande])
            ->assertStatus(422);

        $respuesta = RadicadoRespuesta::create([
            'radicado_id' => $this->radicadoId, 'titulo' => 'Original', 'user_crea_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/ventanilla/radica-recibida/respuestas/{$respuesta->id}", ['contenido' => str_repeat('a', 2_000_001)])
            ->assertStatus(422);
        $this->actingAs($this->user)
            ->putJson("/api/ventanilla/radica-recibida/respuestas/{$respuesta->id}", ['titulo' => ['no es texto']])
            ->assertStatus(422);
        $this->actingAs($this->user)
            ->putJson("/api/ventanilla/radica-recibida/respuestas/{$respuesta->id}", ['titulo' => 'Nuevo'])
            ->assertOk();
    }
}
