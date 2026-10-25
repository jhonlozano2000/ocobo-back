<?php

namespace Tests\Feature\MiBandeja;

use App\Events\MiBandeja\Grupos\NotaCreada;
use App\Events\MiBandeja\Grupos\NotaTyping;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 19.23: notas e indicador «escribiendo…» por Reverb. /broadcasting/auth firmaba con un valor
 * inválido y devolvía el secreto de Reverb al navegador; channels.php no se cargaba.
 */
class NotasTiempoRealTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM = 'Mi Bandeja - Grupos Colaborativos -> Ver';

    private const SOCKET = '1234.5678';

    private User $creador;

    private User $firmante;

    private User $ajeno;

    private MiBandejaTemp $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        Permission::findOrCreate(self::PERM, 'web');

        [$this->creador, $this->firmante, $this->ajeno] = User::factory()->count(3)->create(['estado' => 1])->all();
        foreach ([$this->creador, $this->firmante, $this->ajeno] as $u) {
            $u->givePermissionTo(self::PERM);
        }

        Schema::disableForeignKeyConstraints();
        $this->grupo = MiBandejaTemp::create([
            'nombre' => 'Grupo 19.23', 'asunto' => 'Notas', 'radicado_id' => 999999, 'radicado_tipo' => 'recibido',
            'estado' => 'en_curso', 'estado_grupo' => 'activo', 'usua_crea_id' => $this->creador->id,
        ]);
        Schema::enableForeignKeyConstraints();

        MiBandejaTempGrupoFirmante::create([
            'grupo_id' => $this->grupo->id, 'user_id' => $this->firmante->id, 'orden_firma' => 1, 'estado_tarea' => 'pendiente',
        ]);
    }

    private function autorizar(?User $user, string $canal)
    {
        $peticion = $user ? $this->actingAs($user) : $this;

        return $peticion->postJson('/broadcasting/auth', ['socket_id' => self::SOCKET, 'channel_name' => $canal]);
    }

    public function test_un_miembro_recibe_la_firma_valida_del_canal_del_grupo_sin_el_secreto(): void
    {
        $canal = "private-grupo-colaborativo.{$this->grupo->id}";
        $secreto = config('broadcasting.connections.reverb.secret');
        $esperada = config('broadcasting.connections.reverb.key').':'.hash_hmac('sha256', self::SOCKET.':'.$canal, $secreto);

        foreach ([$this->creador, $this->firmante] as $miembro) {
            $respuesta = $this->autorizar($miembro, $canal)->assertOk()->assertJsonPath('auth', $esperada);

            $this->assertStringNotContainsString($secreto, $respuesta->getContent());
            $this->assertArrayNotHasKey('shared_secret', $respuesta->json());
        }
    }

    public function test_un_ajeno_no_se_suscribe(): void
    {
        $this->autorizar($this->ajeno, "private-grupo-colaborativo.{$this->grupo->id}")->assertForbidden();
        $this->autorizar($this->ajeno, 'private-grupo-colaborativo.999999999')->assertForbidden();
    }

    public function test_sin_sesion_no_hay_firma(): void
    {
        $this->autorizar(null, "private-grupo-colaborativo.{$this->grupo->id}")->assertUnauthorized();
    }

    public function test_el_canal_de_notificaciones_es_solo_del_propio_usuario(): void
    {
        $this->autorizar($this->ajeno, "private-user.{$this->ajeno->id}")->assertOk();
        $this->autorizar($this->ajeno, "private-user.{$this->creador->id}")->assertForbidden();
    }

    public function test_typing_lo_emite_un_miembro_y_no_un_ajeno(): void
    {
        Event::fake([NotaTyping::class]);
        $url = "/api/mi-bandeja/grupos-colaborativos/{$this->grupo->id}/notas/typing";

        $this->actingAs($this->firmante)->postJson($url, ['typing' => true])->assertOk();
        Event::assertDispatched(NotaTyping::class, fn (NotaTyping $e) => $e->userId === $this->firmante->id
            && $e->typing === true
            && $e->broadcastOn()[0]->name === "private-grupo-colaborativo.{$this->grupo->id}");

        Event::fake([NotaTyping::class]);
        $this->actingAs($this->ajeno)->postJson($url, ['typing' => true])->assertForbidden();
        Event::assertNotDispatched(NotaTyping::class);
    }

    public function test_crear_una_nota_la_emite_al_canal_del_grupo(): void
    {
        Event::fake([NotaCreada::class]);

        $this->actingAs($this->creador)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupo->id}/notas", ['contenido' => 'Hola equipo'])
            ->assertCreated();

        Event::assertDispatched(NotaCreada::class, fn (NotaCreada $e) => $e->broadcastWith()['contenido'] === 'Hola equipo'
            && $e->broadcastOn()[0]->name === "private-grupo-colaborativo.{$this->grupo->id}");
    }
}
