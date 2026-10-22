<?php

namespace Tests\Feature\MiBandeja;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Grupo colaborativo de un documento de Mis Documentos: el creador arma el grupo con el formulario de Grupos
 * colaborativos; queda en mi_bandeja_temp vinculado al documento y el acceso de los miembros sale de esa membresía.
 */
class GrupoDocumentoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private User $creador;

    private User $revisor;

    private User $firmante;

    private int $documento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->creador = User::factory()->create(['estado' => 1]);
        $this->revisor = User::factory()->create(['estado' => 1, 'nombres' => 'Rita', 'apellidos' => 'Gómez']);
        $this->firmante = User::factory()->create(['estado' => 1, 'nombres' => 'Fabio', 'apellidos' => 'Díaz']);
        $this->documento = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->creador->id, 'titulo' => 'Oficio de respuesta', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function url(): string
    {
        return self::URL.'/'.$this->documento.'/grupo';
    }

    public function test_crea_el_grupo_del_documento_con_sus_miembros(): void
    {
        $this->actingAs($this->creador)->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.puede_gestionar', true)
            ->assertJsonPath('data.grupo', null);

        $this->actingAs($this->creador)->postJson($this->url(), [
            'asunto' => 'Respuesta al derecho de petición',
            'descripcion' => 'Revisar cifras',
            'revisores' => [$this->revisor->id],
            'aprobadores' => [$this->revisor->id],
            'firmantes' => [$this->firmante->id, $this->creador->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Oficio de respuesta')
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonPath('data.miembros.revisores.0.nombre', 'Rita Gómez')
            ->assertJsonPath('data.miembros.firmantes.0.user_id', $this->firmante->id)
            ->assertJsonPath('data.miembros.firmantes.1.user_id', $this->creador->id)
            ->assertJsonPath('data.miembros.firmantes.0.estado_tarea', 'pendiente');

        $grupo = DB::table('mi_bandeja_temp')->where('documento_id', $this->documento)->first();
        $this->assertSame('activo', $grupo->estado_grupo);
        $this->assertSame(2, DB::table('mi_bandeja_temp_grupo_firmantes')->where('grupo_id', $grupo->id)
            ->where('user_id', $this->creador->id)->value('orden_firma'));

        // Una sola lógica: el acceso sale de la membresía del grupo, sin copiar colaboradores al documento
        $this->assertSame(0, DB::table('mi_bandeja_temp_reci_usuarios')->where('documento_id', $this->documento)->count());
        $this->actingAs($this->revisor)->getJson(self::URL.'/'.$this->documento)->assertOk();
        $this->actingAs($this->firmante)->getJson(self::URL)
            ->assertOk()
            ->assertJsonFragment(['id' => $this->documento]);
        $modelo = Documento::find($this->documento);
        $this->assertTrue($modelo->puedeEditar($this->revisor));
        $this->assertSame('firmante', $modelo->rolEnGrupo($this->firmante));
        $this->assertSame([$this->firmante->id, $this->creador->id], $modelo->firmantesRequeridos()->all());
        $this->actingAs($this->revisor)->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.puede_gestionar', false)
            ->assertJsonPath('data.grupo.id', $grupo->id);

        // Un solo grupo activo por documento
        $this->actingAs($this->creador)->postJson($this->url(), [
            'asunto' => 'Otro', 'revisores' => [$this->revisor->id], 'firmantes' => [$this->firmante->id],
        ])->assertUnprocessable();
    }

    public function test_valida_el_formulario_y_solo_el_creador_lo_crea(): void
    {
        $this->actingAs($this->creador)->postJson($this->url(), [
            'revisores' => [],
            'firmantes' => [999999999],
            'radicado_id' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asunto', 'revisores', 'firmantes.0', 'radicado_tipo']);

        $this->actingAs($this->revisor)->postJson($this->url(), [
            'asunto' => 'Ajeno', 'revisores' => [$this->revisor->id], 'firmantes' => [$this->firmante->id],
        ])->assertForbidden();
    }
}
