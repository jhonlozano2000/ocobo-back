<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * M19-52: el listado y el detalle de instancias de un workflow mostraban todas sus instancias a
 * cualquiera con "Consultar", y ejecutar/detener/devolver no comprobaban acceso a la instancia.
 */
class VisibilidadInstanciasTest extends TestCase
{
    use DatabaseTransactions;

    private User $creador;

    private Workflow $workflow;

    /** @var array<string, int> */
    private array $instancias = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Workflows -> Instancias -> Consultar', 'Workflows -> Instancias -> Ejecutar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->creador = $this->usuario();
        $this->workflow = Workflow::create(['nombre' => 'M19-52', 'estado' => 'activo', 'creador_user_id' => $this->creador->id]);
        Schema::disableForeignKeyConstraints();
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();
        parent::tearDown();
    }

    private function usuario(): User
    {
        $user = User::factory()->create(['estado' => 1]);
        $user->givePermissionTo(['Workflows -> Instancias -> Consultar', 'Workflows -> Instancias -> Ejecutar']);

        return $user;
    }

    private function instancia(User $ejecuta, array $extra = []): int
    {
        return WorkflowInstancia::create([
            'workflow_id' => $this->workflow->id, 'estado' => 'en_curso',
            'usuario_ejecuta_id' => $ejecuta->id, 'fecha_inicio' => now(),
        ] + $extra)->id;
    }

    private function listado(User $user): array
    {
        return collect($this->actingAs($user)->getJson("/api/workflows/workflows/{$this->workflow->id}/instancias?per_page=100")
            ->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_cada_usuario_ve_solo_las_instancias_en_que_participa(): void
    {
        $ejecutor = $this->usuario();
        $responsable = $this->usuario();
        $delRadicado = $this->usuario();
        $ajeno = $this->usuario();

        $propia = $this->instancia($ejecutor);
        $conTarea = $this->instancia($this->creador);
        $tarea = DB::table('workflow_tareas')->insertGetId([
            'workflow_id' => $this->workflow->id, 'instancia_id' => $conTarea, 'nombre' => 'Revisar', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tarea_responsables')->insert(['tarea_id' => $tarea, 'user_id' => $responsable->id]);
        $radicado = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M1952-'.uniqid(), 'asunto' => 'Origen', 'medio_recep_id' => 1, 'usuario_crea' => $delRadicado->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $deRadicado = $this->instancia($this->creador, ['origen_tipo' => 'radicado_recibido', 'origen_id' => $radicado, 'radicado_id' => $radicado]);

        $this->assertSame([$propia], $this->listado($ejecutor));
        $this->assertSame([$conTarea], $this->listado($responsable));
        $this->assertSame([$deRadicado], $this->listado($delRadicado));
        $this->assertSame([], $this->listado($ajeno));
        $this->assertSame([$propia, $conTarea, $deRadicado], $this->listado($this->creador));

        $admin = $this->usuario();
        $admin->assignRole(Role::findOrCreate('Administrador', 'web'));
        $this->assertSame([$propia, $conTarea, $deRadicado], $this->listado($admin));

        $this->actingAs($responsable)->getJson("/api/workflows/workflows/{$this->workflow->id}/instancias/{$conTarea}")->assertOk();
        $this->actingAs($ajeno)->getJson("/api/workflows/workflows/{$this->workflow->id}/instancias/{$conTarea}")->assertNotFound();
    }

    public function test_quien_no_ve_la_instancia_no_la_opera(): void
    {
        $ajeno = $this->usuario();
        $id = $this->instancia($this->creador);
        $base = "/api/workflows/workflows/{$this->workflow->id}/instancias/{$id}";

        $this->actingAs($ajeno)->postJson("{$base}/ejecutar", ['nodo_id' => 1])->assertNotFound();
        $this->actingAs($ajeno)->patchJson("{$base}/detener", ['estado' => 'cancelada'])->assertNotFound();
        $this->actingAs($ajeno)->postJson("{$base}/devolver", ['nodo_origen_id' => 1, 'nodo_destino_id' => 2, 'motivo' => 'Corregir'])->assertNotFound();

        $this->assertDatabaseHas('workflow_instancias', ['id' => $id, 'estado' => 'en_curso']);
    }
}
