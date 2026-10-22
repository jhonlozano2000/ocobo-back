<?php

namespace Tests\Feature\MiBandeja;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Grupo colaborativo de un documento de Mis Documentos: se crea con el mismo flujo de Grupos colaborativos
 * (POST /grupos-colaborativos con documento_id y luego sus miembros) y el acceso de los miembros al documento
 * sale de esa membresía.
 */
class GrupoDocumentoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private const URL_GRUPOS = '/api/mi-bandeja/grupos-colaborativos';

    private const PERMISOS = ['Ver', 'Crear', 'Gestionar Miembros'];

    private User $creador;

    private User $revisor;

    private User $firmante;

    private int $documento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        $permisos = array_map(fn ($p) => "Mi Bandeja - Grupos Colaborativos -> {$p}", self::PERMISOS);
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->creador = User::factory()->create(['estado' => 1]);
        $this->revisor = User::factory()->create(['estado' => 1, 'nombres' => 'Rita', 'apellidos' => 'Gómez']);
        $this->firmante = User::factory()->create(['estado' => 1, 'nombres' => 'Fabio', 'apellidos' => 'Díaz']);
        $this->creador->givePermissionTo($permisos);
        $this->revisor->givePermissionTo($permisos);
        $this->documento = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->creador->id, 'titulo' => 'Oficio de respuesta', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function url(): string
    {
        return self::URL.'/'.$this->documento.'/grupo';
    }

    private function crearGrupo(User $user): TestResponse
    {
        return $this->actingAs($user)->postJson(self::URL_GRUPOS, [
            'nombre' => 'Oficio de respuesta',
            'asunto' => 'Respuesta al derecho de petición',
            'documento_id' => $this->documento,
        ]);
    }

    public function test_el_grupo_del_documento_se_crea_con_el_flujo_de_grupos_colaborativos(): void
    {
        $this->actingAs($this->creador)->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.puede_gestionar', true)
            ->assertJsonPath('data.grupo', null);

        $grupoId = $this->crearGrupo($this->creador)->assertCreated()->json('data.id');
        $miembros = [
            'revisores' => [$this->revisor->id],
            'aprobadores' => [$this->revisor->id],
            'firmantes' => [$this->firmante->id, $this->creador->id],
        ];
        foreach ($miembros as $rol => $ids) {
            foreach ($ids as $i => $userId) {
                $this->postJson(self::URL_GRUPOS."/{$grupoId}/{$rol}", ['user_id' => $userId, 'orden_firma' => $i + 1])
                    ->assertCreated();
            }
        }

        $this->assertSame($this->documento, DB::table('mi_bandeja_temp')->where('id', $grupoId)->value('documento_id'));
        $this->actingAs($this->creador)->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.grupo.id', $grupoId)
            ->assertJsonPath('data.grupo.estado', 'borrador')
            ->assertJsonPath('data.grupo.miembros.revisores.0.nombre', 'Rita Gómez')
            ->assertJsonPath('data.grupo.miembros.firmantes.0.user_id', $this->firmante->id)
            ->assertJsonPath('data.grupo.miembros.firmantes.1.user_id', $this->creador->id)
            ->assertJsonPath('data.grupo.miembros.firmantes.0.estado_tarea', 'pendiente');

        // El acceso sale de la membresía del grupo, sin copiar colaboradores al documento
        $this->assertSame(0, DB::table('mi_bandeja_temp_reci_usuarios')->where('documento_id', $this->documento)->count());
        $this->actingAs($this->revisor)->getJson(self::URL.'/'.$this->documento)->assertOk();
        $this->actingAs($this->revisor)->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.puede_gestionar', false)
            ->assertJsonPath('data.grupo.id', $grupoId);
        $this->actingAs($this->firmante)->getJson(self::URL)
            ->assertOk()
            ->assertJsonFragment(['id' => $this->documento]);
        $modelo = Documento::find($this->documento);
        $this->assertTrue($modelo->puedeEditar($this->revisor));
        $this->assertSame('firmante', $modelo->rolEnGrupo($this->firmante));
        $this->assertSame([$this->firmante->id, $this->creador->id], $modelo->firmantesRequeridos()->all());

        // Un solo grupo activo por documento
        $this->crearGrupo($this->creador)->assertUnprocessable();
    }

    public function test_solo_el_dueno_del_documento_crea_su_grupo(): void
    {
        // El revisor tiene los permisos de grupos, pero el documento no es suyo
        $this->crearGrupo($this->revisor)->assertForbidden();
        $this->assertSame(0, DB::table('mi_bandeja_temp')->where('documento_id', $this->documento)->count());

        $this->actingAs($this->creador)->postJson(self::URL_GRUPOS, [
            'nombre' => 'Sin documento', 'documento_id' => 999999999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['documento_id']);
    }

    public function test_sin_permisos_de_grupos_el_dueno_no_puede_crearlo(): void
    {
        // El firmante es dueño de un documento, pero no tiene permisos de Grupos colaborativos
        DB::table('mi_bandeja_temp_reci_documentos')->where('id', $this->documento)->update(['user_id' => $this->firmante->id]);

        $this->actingAs($this->firmante)->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.puede_gestionar', false);
        $this->crearGrupo($this->firmante)->assertForbidden();
    }
}
