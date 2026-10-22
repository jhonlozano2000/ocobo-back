<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Compartir un documento de Mis Documentos: el creador arma el grupo colaborativo (funcionarios con rol), los
 * colaboradores lo ven en su lista y lo editan, y al quitarlos pierden el acceso.
 */
class ColaboradoresDocumentoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private User $creador;

    private User $otro;

    private int $documento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->creador = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Ruiz']);
        $this->otro = User::factory()->create(['estado' => 1, 'nombres' => 'Luis', 'apellidos' => 'Pérez']);
        $this->documento = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->creador->id, 'titulo' => 'Oficio compartido', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function url(string $resto = ''): string
    {
        return self::URL.'/'.$this->documento.'/usuarios'.$resto;
    }

    public function test_el_creador_comparte_y_el_colaborador_lo_ve_y_edita(): void
    {
        $this->actingAs($this->otro)->getJson(self::URL.'/'.$this->documento)->assertForbidden();

        $this->actingAs($this->creador)
            ->postJson($this->url(), ['usuarios' => [['user_id' => $this->otro->id, 'rol' => 'proyector']]])
            ->assertOk()
            ->assertJsonPath('data.puede_gestionar', true)
            ->assertJsonPath('data.creador.nombre', 'Ana Ruiz')
            ->assertJsonPath('data.colaboradores.0.nombre', 'Luis Pérez')
            ->assertJsonPath('data.colaboradores.0.rol', 'proyector');

        $this->actingAs($this->otro)->getJson(self::URL)
            ->assertOk()
            ->assertJsonFragment(['titulo' => 'Oficio compartido']);
        $this->actingAs($this->otro)
            ->putJson(self::URL.'/'.$this->documento, ['titulo' => 'Oficio editado'])
            ->assertOk();

        // El colaborador ve quién tiene acceso, pero no gestiona el grupo
        $this->actingAs($this->otro)->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.puede_gestionar', false);
        $this->actingAs($this->otro)->deleteJson($this->url('/'.$this->otro->id))->assertForbidden();
    }

    public function test_cambiar_el_rol_y_quitar_al_colaborador(): void
    {
        $this->actingAs($this->creador)
            ->postJson($this->url(), ['usuarios' => [['user_id' => $this->otro->id, 'rol' => 'proyector']]]);
        $this->actingAs($this->creador)
            ->postJson($this->url(), ['usuarios' => [['user_id' => $this->otro->id, 'rol' => 'firmante']]])
            ->assertOk()
            ->assertJsonCount(1, 'data.colaboradores')
            ->assertJsonPath('data.colaboradores.0.rol', 'firmante');

        $this->actingAs($this->creador)->deleteJson($this->url('/'.$this->otro->id))
            ->assertOk()
            ->assertJsonCount(0, 'data.colaboradores');
        $this->actingAs($this->otro)->getJson(self::URL.'/'.$this->documento)->assertForbidden();
        $this->actingAs($this->creador)->deleteJson($this->url('/'.$this->otro->id))->assertNotFound();
    }

    public function test_valida_rol_creador_e_inactivos(): void
    {
        $inactivo = User::factory()->create(['estado' => 0]);

        $this->actingAs($this->creador)
            ->postJson($this->url(), ['usuarios' => [
                ['user_id' => $this->creador->id, 'rol' => 'proyector'],
                ['user_id' => $inactivo->id, 'rol' => 'firmante'],
                ['user_id' => $this->otro->id, 'rol' => 'lector'],
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usuarios.0.user_id', 'usuarios.1.user_id', 'usuarios.2.rol']);
    }
}
