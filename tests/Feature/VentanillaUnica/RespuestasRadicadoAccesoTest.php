<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\RadicadoRespuesta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M01-10: las rutas de respuestas por id no validaban el acceso al radicado y cualquiera con
 * "Editar" modificaba respuestas ajenas.
 */
class RespuestasRadicadoAccesoTest extends TestCase
{
    use DatabaseTransactions;

    private User $autor;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->autor = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        foreach (['Mostrar', 'Editar'] as $accion) {
            $permiso = Permission::findOrCreate('Radicar -> Cores. Recibida -> '.$accion, 'web');
            $this->autor->givePermissionTo($permiso);
            $this->otro->givePermissionTo($permiso);
        }
    }

    private function respuesta(): RadicadoRespuesta
    {
        Schema::disableForeignKeyConstraints();
        $radicadoId = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0110-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->autor->id,
        ]);
        Schema::enableForeignKeyConstraints();

        return RadicadoRespuesta::create([
            'radicado_id' => $radicadoId,
            'titulo' => 'Original',
            'user_crea_id' => $this->autor->id,
        ]);
    }

    public function test_usuario_sin_acceso_al_radicado_no_ve_ni_edita_la_respuesta(): void
    {
        $respuesta = $this->respuesta();

        $this->actingAs($this->otro)->getJson("/api/ventanilla/radica-recibida/respuestas/{$respuesta->id}")->assertNotFound();
        $this->actingAs($this->otro)->putJson("/api/ventanilla/radica-recibida/respuestas/{$respuesta->id}", ['titulo' => 'Cambiado'])->assertNotFound();

        $this->assertSame('Original', $respuesta->fresh()->titulo);
    }

    public function test_solo_el_autor_edita_su_respuesta(): void
    {
        $respuesta = $this->respuesta();
        $this->otro->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Recibida -> Ver Todos', 'web'));

        $this->actingAs($this->otro)->getJson("/api/ventanilla/radica-recibida/respuestas/{$respuesta->id}")->assertOk();
        $this->actingAs($this->otro)->putJson("/api/ventanilla/radica-recibida/respuestas/{$respuesta->id}", ['titulo' => 'Cambiado'])->assertForbidden();
        $this->actingAs($this->autor)->putJson("/api/ventanilla/radica-recibida/respuestas/{$respuesta->id}", ['titulo' => 'Cambiado'])->assertOk();

        $this->assertSame('Cambiado', $respuesta->fresh()->titulo);
    }
}
