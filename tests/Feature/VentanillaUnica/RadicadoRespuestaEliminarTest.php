<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\RadicadoRespuesta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M01-09: DELETE /radica-recibida/respuestas/{id} tipaba el id como Request y
 * respondía 500 siempre.
 */
class RadicadoRespuestaEliminarTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(): User
    {
        $permiso = 'Radicar -> Cores. Recibida -> Eliminar';
        Permission::findOrCreate($permiso, 'web');
        $user = User::factory()->create(['estado' => 1]);
        $user->givePermissionTo($permiso);

        return $user;
    }

    private function respuesta(User $autor): RadicadoRespuesta
    {
        // El radicado no es relevante para estas pruebas
        Schema::disableForeignKeyConstraints();
        $respuesta = RadicadoRespuesta::create([
            'radicado_id' => 999999,
            'titulo' => 'Borrador M01-09',
            'user_crea_id' => $autor->id,
        ]);
        Schema::enableForeignKeyConstraints();

        return $respuesta;
    }

    public function test_el_autor_elimina_su_respuesta(): void
    {
        $autor = $this->usuario();
        $respuesta = $this->respuesta($autor);

        $this->actingAs($autor)->deleteJson('/api/ventanilla/radica-recibida/respuestas/'.$respuesta->id)->assertOk();

        $this->assertDatabaseMissing('ventanilla_radica_reci_respuestas', ['id' => $respuesta->id]);
    }

    public function test_otro_usuario_no_puede_eliminarla(): void
    {
        $respuesta = $this->respuesta($this->usuario());

        $this->actingAs($this->usuario())->deleteJson('/api/ventanilla/radica-recibida/respuestas/'.$respuesta->id)->assertForbidden();

        $this->assertNotNull($respuesta->fresh());
    }
}
